<?php

namespace MarineSync\Importers;

/**
 * Interface BoatImporter
 *
 * Defines the methods for importing boat data.
 */
interface BoatImporter {
	public function importBoatData($path): array;
	public function processBoatData($data): void;
}