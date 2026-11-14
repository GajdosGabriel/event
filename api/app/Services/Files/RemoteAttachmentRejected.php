<?php

namespace App\Services\Files;

use InvalidArgumentException;

/**
 * Príloha sa zámerne neuloží: cieľ mimo verejného webu, nepovolený typ,
 * obsah nesedí s typom alebo je priveľká. Na rozdiel od výpadku siete nemá
 * zmysel ju opakovať.
 */
class RemoteAttachmentRejected extends InvalidArgumentException {}
