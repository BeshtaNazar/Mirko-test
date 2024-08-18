<?php

namespace App\Filter;

class PostFilter
{
    public function __construct
    (
        public readonly ?string $title,
        public readonly ?string $authorEmail,
        public readonly ?string $content,
    ) {
    }
}