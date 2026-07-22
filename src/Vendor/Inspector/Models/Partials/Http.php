<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\Inspector\Models\Partials;

use NeuronAi\Vendor\Inspector\Models\Model;
class Http extends Model
{
    /**
     * Http constructor.
     */
    public function __construct(public Request $request = new Request(), public Url $url = new Url())
    {
    }
}
