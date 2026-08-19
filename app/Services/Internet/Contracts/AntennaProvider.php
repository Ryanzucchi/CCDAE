<?php

namespace App\Services\Internet\Contracts;

interface AntennaProvider
{
    public function antennasForBbox(array $bbox): array;

    public function source(): string;
}
