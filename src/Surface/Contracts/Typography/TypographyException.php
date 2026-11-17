<?php

namespace Surface\Contracts\Typography;

use Surface\Contracts\Core\TopsideException;

/**
 * What Typography throws: a request it cannot answer, a font it cannot read, text that is not UTF-8.
 * A driver whose library is not loaded throws it from its constructor.
 */
class TypographyException extends TopsideException
{
}
