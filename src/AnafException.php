<?php

namespace Gabrieldev\Anaf;

use RuntimeException;

/** ANAF could not be reached or answered with an error. Unknown CUIs are not errors (they return null). */
class AnafException extends RuntimeException {}
