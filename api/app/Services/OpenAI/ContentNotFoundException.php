<?php

namespace App\Services\OpenAI;

/**
 * Stránka sa stiahla, ale nie je v nej čo čítať — chýba element s hlavným
 * obsahom (napr. JS aplikácia, ktorá obsah vykresľuje až v prehliadači).
 * Na rozdiel od výpadku servera sa to opakovaním nezmení.
 */
class ContentNotFoundException extends \RuntimeException {}
