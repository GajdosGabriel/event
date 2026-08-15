<?php

return [
    'errors' => [
        'input_missing'    => 'Upload a poster or paste the text of the invitation.',
        'file_mimes'       => 'We support PDF, Word (.docx), a photo of the poster (JPG, PNG, WEBP) or a text file.',
        'file_max'         => 'The file is too large — the maximum is 12 MB.',
        'text_min'         => 'The text is too short to build an event from.',
        'end_before_start' => 'The event cannot end before it starts.',
    ],

    'extract' => [
        'empty_text' => 'The text is empty.',
        'doc_legacy' => 'We cannot read the old .doc format. Save the document as .docx or PDF and try again.',
        'unsupported' => 'We do not support this file format. Upload a PDF, Word (.docx), an image of the poster or a text file.',
        'image_unreadable' => 'The image could not be read.',
        'image_too_large' => 'The image is too large. Try to get it under 12 MB.',
        'pdf_too_large_limit' => 'The PDF is too large to process (limit :limit MB). Shrink it or upload the poster as an image.',
        'pdf_too_large' => 'The PDF is too large to process. Shrink it (e.g. by exporting at a lower quality) or upload the poster as an image.',
        'pdf_failed' => 'The PDF could not be processed. Try again in a moment, or upload the poster as an image.',
        'pdf_empty' => 'Nothing could be read from this PDF. Try uploading the poster as an image (JPG/PNG).',
        'zip_missing' => 'The ZIP extension is missing on the server, we cannot read .docx.',
        'docx_unreadable' => 'The .docx file could not be opened — it is probably damaged.',
        'docx_no_text' => 'No text was found in the .docx document.',
        'no_text' => 'The document contains no text — the details are probably inside an image. Upload the poster as an image or a PDF.',
    ],

    'draft' => [
        'analyze_failed' => 'The poster could not be processed. Please try again in a moment.',
        'save_failed' => 'We read the poster but could not save it. Please try again.',
        'link_sent' => 'We have sent the link to the draft event to :email.',
        'login_required' => 'You have to sign in to save the event.',
        'token_missing' => 'The access token is missing.',
        'not_found' => 'The draft poster was not found.',
        'expired' => 'The draft poster has expired. Please upload it again.',
    ],
];
