<?php

namespace Tests\Support;

use Eris\Attributes\ErisRatio;
use Eris\Attributes\ErisRepeat;
use Eris\TestTrait;
use Tests\TestCase;

#[ErisRepeat(100)]
#[ErisRatio(100)]
abstract class PropertyTestCase extends TestCase
{
    use TestTrait;
}
