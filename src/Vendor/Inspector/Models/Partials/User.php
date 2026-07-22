<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\Inspector\Models\Partials;

use NeuronAi\Vendor\Inspector\Models\Model;
class User extends Model
{
    public function __construct(public string|int|null $id = null, public ?string $name = null, public ?string $email = null)
    {
    }
}
