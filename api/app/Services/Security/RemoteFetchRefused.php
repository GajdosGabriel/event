<?php

namespace App\Services\Security;

use RuntimeException;

/** SafeRemoteFetcher odmietol adresu alebo odpoveď (nie chyba siete). */
class RemoteFetchRefused extends RuntimeException {}
