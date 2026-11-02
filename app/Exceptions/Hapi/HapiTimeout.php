<?php

namespace App\Exceptions\Hapi;

/**
 * HAPI took too long to answer. For a write, HAPI may still have applied it.
 */
class HapiTimeout extends HapiException {}
