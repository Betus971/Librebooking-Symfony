<?php

namespace App\Dto;

class ResourceSearchCriteria
{
    public function __construct(
        public ?int $typeId = null,
        public ?int $minCapacity = null,
        public ?bool $onlyActive = true,
    ) {
    }

}
