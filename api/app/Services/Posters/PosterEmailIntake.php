<?php

namespace App\Services\Posters;

use App\Models\EmailSuppression;
use App\Models\PosterDraft;
use App\Notifications\PosterEmailReply;
use App\Services\Account\AccountClient;
use App\Services\OpenAI\Detector;
use App\Services\SystemLog\Recorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Podujatie poslané e-mailom na kontaktnú adresu — tretia cesta k tomu istému
 * konceptu ako „nahrajte plagát" (súbor alebo vložený text na webe).
 *
 *  1. Nový e-mail mimo konverzácie (`message.unassigned` z Accountu): ak v ňom
 *     AI rozpozná pozvánku, vznikne PosterDraft z textu a prvej prílohy
 *     a odosielateľ dostane odpoveď do tej istej konverzácie — čo sme
 *     pochopili, čo chýba a odkaz na koncept. Odpoveď si konverzáciu priradí
 *     (ref `poster_draft`), takže ďalšia správa odosielateľa príde sem.
 *  2. Odpoveď odosielateľa (`message.received`): doplnenie sa pridá k textu,
 *     rozbor sa zopakuje a ide ďalšia odpoveď. Najviac `max_rounds` kôl.
 *
 * Na kontakt chodí aj bežná pošta, preto sa e-mail, ktorý pozvánkou nie je
 * (alebo je to žiadosť o opravu existujúceho podujatia s odkazom naň), nechá
 * nepriradený v inboxe Accountu operátorovi. Z e-mailu sa nikdy nič
 * nezverejní: podujatie vznikne až potvrdením konceptu (PosterController::claim).
 */
class PosterEmailIntake
{
    public const ACCOUNT_REF = 'poster_draft';

    private const DRAFT_TTL_DAYS = 7;

    private const DISK = 'local';

    /** Rovnaký limit ako pri nahratí na webe (PosterAnalyzeRequest). */
    private const MAX_ATTACHMENT_BYTES = 12 * 1024 * 1024;

    private const ATTACHMENT_EXTENSIONS = ['pdf', 'docx', 'jpg', 'jpeg', 'png', 'webp'];

    private const MIN_TEXT = 30;

    public function __construct(
        private readonly Detector $detector,
        private readonly PosterTextExtractor $extractor,
        private readonly PosterAnalysisReport $report,
        private readonly AccountClient $account,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.poster_email.enabled') && $this->account->messagingEnabled();
    }

    /** Adresa na zobrazenie na webe, alebo null, keď je príjem vypnutý. */
    public function inbox(): ?string
    {
        $inbox = trim((string) config('services.poster_email.inbox'));

        return $this->enabled() && $inbox !== '' ? $inbox : null;
    }

    /**
     * Nový e-mail mimo konverzácie.
     *
     * @param  array<string, mixed>  $conversation
     * @param  array<string, mixed>  $message
     */
    public function unassigned(array $conversation, array $message): void
    {
        $conversationId = (string) ($conversation['id'] ?? '');
        $from = $this->sender($message);

        if (! $this->enabled() || $conversationId === '' || $from === null || ! $this->fromInbox($conversation)) {
            return;
        }

        // Webhook sa môže doručiť znova — koncept k tejto konverzácii už máme.
        if (PosterDraft::query()->where('account_conversation_id', $conversationId)->exists()) {
            return;
        }

        $text = $this->messageText($message);
        $attachment = $this->attachment($message);

        if (! $this->looksLikeInvitation($text, $attachment !== null)) {
            return;
        }

        if (! $this->withinLimits($from)) {
            $this->log('limited', $from, $message);

            return;
        }

        $extraction = $this->extract($text, $attachment);

        if ($extraction === null) {
            return;
        }

        $detection = $this->detector->detectFromPoster($extraction->text, $extraction->imageDataUrls);

        if (! $this->isInvitation($detection)) {
            $this->log('not_an_event', $from, $message);

            return;
        }

        $token = Str::random(64);
        $draft = new PosterDraft([
            'email' => $from,
            'source_kind' => $extraction->kind,
            'original_filename' => $attachment?->getClientOriginalName(),
            'file_disk' => $attachment !== null ? self::DISK : null,
            'file_path' => $attachment !== null ? ($attachment->store('poster-drafts', self::DISK) ?: null) : null,
            'extracted_text' => $extraction->text,
            'detection' => $detection,
            'analysis' => $this->report->build($detection, $extraction),
            'expires_at' => now()->addDays(self::DRAFT_TTL_DAYS),
        ]);
        $draft->token = PosterDraft::hashToken($token);
        $draft->forceFill([
            'channel' => PosterDraft::CHANNEL_EMAIL,
            'account_conversation_id' => $conversationId,
            'last_message_id' => $this->messageId($message),
            'email_rounds' => 1,
            'email_token' => $token,
        ])->save();

        $this->reply($draft, (string) ($message['subject'] ?? ''));
        $this->log('draft_created', $from, $message, $draft);
    }

    /**
     * Odpoveď odosielateľa v konverzácii konceptu.
     *
     * @param  array<string, mixed>  $message
     */
    public function received(PosterDraft $draft, array $message): void
    {
        $messageId = $this->messageId($message);

        if (! $this->enabled() || $draft->isClaimed() || $draft->isExpired()
            || ($messageId !== null && $draft->last_message_id === $messageId)) {
            return;
        }

        // Po poslednom kole odpoveď ostane v inboxe Accountu — prečíta ju človek.
        if ($draft->email_rounds >= (int) config('services.poster_email.max_rounds', 3)) {
            $this->log('rounds_exhausted', $draft->email, $message, $draft);

            return;
        }

        $addition = $this->messageText($message);
        $attachment = $draft->file_path === null ? $this->attachment($message) : null;

        if (mb_strlen($addition) < 2 && $attachment === null) {
            return;
        }

        $text = trim((string) $draft->extracted_text."\n\n".__('poster.email.addition_heading')."\n".$addition);
        // Text prílohy už je v `extracted_text`; obrázok plagátu sa neukladá,
        // tak ho pre vision prečítame zo súboru konceptu znova.
        $extraction = $attachment !== null
            ? $this->extract($text, $attachment)
            : new PosterExtraction(text: $text, imageDataUrls: $this->storedImages($draft), kind: (string) $draft->source_kind);

        if ($extraction === null) {
            return;
        }

        $detection = $this->detector->detectFromPoster($extraction->text, $extraction->imageDataUrls);

        // Neúspešný rozbor nesmie zmazať to, čo sme už pochopili.
        if (($detection['success'] ?? false) !== true) {
            $this->log('analysis_failed', $draft->email, $message, $draft);

            return;
        }

        $draft->forceFill([
            'extracted_text' => $extraction->text,
            'detection' => $detection,
            'analysis' => $this->report->build($detection, $extraction),
            'last_message_id' => $messageId,
            'email_rounds' => $draft->email_rounds + 1,
            'expires_at' => now()->addDays(self::DRAFT_TTL_DAYS),
        ]);

        if ($attachment !== null) {
            $draft->forceFill([
                'source_kind' => $extraction->kind,
                'original_filename' => $attachment->getClientOriginalName(),
                'file_disk' => self::DISK,
                'file_path' => $attachment->store('poster-drafts', self::DISK) ?: null,
            ]);
        }

        $draft->save();

        $this->reply($draft, (string) ($message['subject'] ?? ''));
        $this->log('draft_updated', $draft->email, $message, $draft);
    }

    private function reply(PosterDraft $draft, string $subject): void
    {
        Notification::route('mail', $draft->email)->notify(new PosterEmailReply(
            draftId: $draft->id,
            conversationId: (string) $draft->account_conversation_id,
            token: (string) $draft->email_token,
            analysis: (array) $draft->analysis,
            subject: $subject,
            expiresAt: $draft->expires_at,
        ));
    }

    /**
     * Lacné sito pred platenou AI: pozvánka má dátum (alebo prílohu-plagát)
     * a nie je to žiadosť o opravu podujatia, ktoré už na portáli je.
     */
    private function looksLikeInvitation(string $text, bool $hasAttachment): bool
    {
        $frontend = preg_quote((string) parse_url((string) config('app.frontend_url'), PHP_URL_HOST), '/');

        if ($frontend !== '' && preg_match('/'.$frontend.'\/akcie\/\d+/i', $text)) {
            return false;
        }

        if (preg_match('/(^|\s)\/akcie\/\d+/i', $text)) {
            return false;
        }

        if ($hasAttachment) {
            return true;
        }

        if (mb_strlen($text) < self::MIN_TEXT) {
            return false;
        }

        $months = 'janu|febru|marc|apríl|april|máj|maj|jún|jun|júl|jul|augus|septem|októ|okto|novem|decem';

        return (bool) preg_match('/\b\d{1,2}\.\s?(\d{1,2}\.|('.$months.'))|\b\d{4}-\d{2}-\d{2}\b/iu', $text);
    }

    /** @param  array<string, mixed>  $detection */
    private function isInvitation(array $detection): bool
    {
        if (($detection['success'] ?? false) !== true) {
            return false;
        }

        $payload = (array) ($detection['event_payload'] ?? []);
        $title = trim((string) ($payload['title'] ?? ''));
        $start = trim((string) ($payload['start_at'] ?? ''));
        $venue = trim((string) ($payload['venue']['name'] ?? ''));

        return $title !== '' && ($start !== '' || $venue !== '');
    }

    private function extract(string $text, ?UploadedFile $attachment): ?PosterExtraction
    {
        try {
            if ($attachment === null) {
                return mb_strlen($text) >= self::MIN_TEXT ? $this->extractor->fromText($text) : null;
            }

            $file = $this->extractor->fromUploadedFile($attachment);

            // Text e-mailu aj príloha: e-mail býva sprievodný list, plagát
            // nesie termín a miesto — AI potrebuje oboje.
            return new PosterExtraction(
                text: trim($text."\n\n".$file->text),
                imageDataUrls: $file->imageDataUrls,
                kind: $file->kind,
                pageCount: $file->pageCount,
            );
        } catch (PosterExtractionException) {
            return mb_strlen($text) >= self::MIN_TEXT ? new PosterExtraction(text: $text, kind: 'text') : null;
        }
    }

    /** @return array<int, string> */
    private function storedImages(PosterDraft $draft): array
    {
        if ($draft->file_path === null || $draft->file_disk === null || ! Storage::disk($draft->file_disk)->exists($draft->file_path)) {
            return [];
        }

        try {
            $file = new UploadedFile(Storage::disk($draft->file_disk)->path($draft->file_path), (string) $draft->original_filename, null, null, true);

            return $this->extractor->fromUploadedFile($file)->imageDataUrls;
        } catch (PosterExtractionException) {
            return [];
        }
    }

    /**
     * Prvá príloha, ktorú vieme prečítať (plagát, PDF, Word), stiahnutá
     * z dočasnej URL Accountu.
     *
     * @param  array<string, mixed>  $message
     */
    private function attachment(array $message): ?UploadedFile
    {
        foreach ((array) ($message['attachments'] ?? []) as $item) {
            $name = (string) ($item['filename'] ?? '');
            $url = (string) ($item['url'] ?? '');
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if ($url === '' || ! in_array($extension, self::ATTACHMENT_EXTENSIONS, true)
                || (int) ($item['size'] ?? 0) > self::MAX_ATTACHMENT_BYTES) {
                continue;
            }

            try {
                $response = Http::timeout(30)->get($url);
            } catch (\Throwable) {
                continue;
            }

            $body = $response->successful() ? $response->body() : '';

            if ($body === '' || strlen($body) > self::MAX_ATTACHMENT_BYTES) {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'poster-mail-');
            file_put_contents($path, $body);

            return new UploadedFile($path, $name, $item['mime'] ?? null, null, true);
        }

        return null;
    }

    /** @param  array<string, mixed>  $message */
    private function messageText(array $message): string
    {
        $text = trim((string) ($message['reply_text'] ?? ''));

        return $text !== '' ? $text : trim((string) ($message['text'] ?? ''));
    }

    /** @param  array<string, mixed>  $message */
    private function sender(array $message): ?string
    {
        $email = mb_strtolower(trim((string) ($message['from'] ?? '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || EmailSuppression::has($email)) {
            return null;
        }

        return $email;
    }

    /** @param  array<string, mixed>  $message */
    private function messageId(array $message): ?string
    {
        $id = trim((string) ($message['id'] ?? ''));

        return $id !== '' ? Str::limit($id, 64, '') : null;
    }

    /** Len pošta do schránky, ktorú ukazujeme na webe (ak je nastavená). */
    private function fromInbox(array $conversation): bool
    {
        $inbox = mb_strtolower(trim((string) config('services.poster_email.inbox')));
        $mailbox = mb_strtolower(trim((string) ($conversation['mailbox'] ?? '')));

        return $inbox === '' || $mailbox === '' || $inbox === $mailbox;
    }

    private function withinLimits(string $from): bool
    {
        $sender = 'poster-email:sender:'.sha1($from);
        $all = 'poster-email:all';

        if (RateLimiter::tooManyAttempts($sender, max(1, (int) config('services.poster_email.per_sender_daily', 3)))
            || RateLimiter::tooManyAttempts($all, max(1, (int) config('services.poster_email.daily', 30)))) {
            return false;
        }

        RateLimiter::hit($sender, 86400);
        RateLimiter::hit($all, 86400);

        return true;
    }

    /** @param  array<string, mixed>  $message */
    private function log(string $event, ?string $from, array $message, ?PosterDraft $draft = null): void
    {
        Recorder::info('posters', 'email_'.$event, 'Podujatie z e-mailu: '.Str::limit((string) ($message['subject'] ?? ''), 120),
            status: $event,
            recipient: $from,
            context: ['draft_id' => $draft?->id, 'message' => $message['id'] ?? null, 'rounds' => $draft?->email_rounds],
        );
    }
}
