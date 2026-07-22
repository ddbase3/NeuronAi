<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Enums;

enum SourceType : string
{
    case URL = 'url';
    case BASE64 = 'base64';
    case ID = 'id';
}
