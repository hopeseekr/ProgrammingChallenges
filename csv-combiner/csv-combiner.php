#!/bin/env php
<?php

declare(strict_types=1);

use PHPExperts\ConsolePainter\ConsolePainter;
use PHPExperts\CSVSpeaker\CSVReader;
use PHPExperts\CSVSpeaker\CSVWriter;

require_once "vendor/autoload.php";

const MAX_ROWS_BUFFER = 1000;

function main($argv)
{
    $p = new ConsolePainter();

    /**
     * @return string[] An array of the CSV files.
     */
    $sanityChecks = function () use ($p, $argv): array {
        if (!array_key_exists(2, $argv)) {
            echo $p->bold()->red('Error: ')->text('At least 2 CSV files must be specified.') . "\n";
            exit(1);
        }

        $filenames = array_splice($argv, 1);
        $csvFiles = [];
        foreach ($filenames as $filename) {
            if (!is_readable($filename)) {
                echo $p->bold()->red('Error: ')->text("Cannot find/read '$filename'.") . "\n";
                exit(2);
            }

            $csvFiles[] = $filename;
        }

        return $csvFiles;
    };

    $csvFiles = $sanityChecks();

    combineCSVs($csvFiles);
}

/**
 * @param string[] An array of the CSV files.
 */
function combineCSVs(array $csvFiles)
{
    /**
     * @param string[] $csvFiles
     * @return string[]
     */
    $findCommonHeaders = function (array $csvFiles): array {
        /** @var array|null $commonHeaders */
        $globalHeaders = null;

        $commonHeaders = [];
        foreach ($csvFiles as $csvFile) {
            $csv = CSVReader::fromFile($csvFile);

            $myHeaders = $csv->getHeaders();
            if ($globalHeaders === null) {
                $globalHeaders = $myHeaders;
            }

            $commonHeaders = array_intersect($globalHeaders, $myHeaders);
        }

        return $commonHeaders;
    };

    // 1. Determine the common headers for all CSV files
    $commonHeaders = $findCommonHeaders($csvFiles);

    // 2. Combine the CSV files' common header columns into one common CSV file and output via STDOUT.
    $csvWriter = new CSVWriter();
    foreach ($csvFiles as $csvFile) {
        $csv = CSVReader::fromFile($csvFile);

        $lineCount = 0;
        foreach ($csv->readCSVGenerator($commonHeaders) as $row) {
            if ($lineCount === 0) {
                ++$lineCount;

                continue;
            }
            $row['filename'] = basename($csvFile);
            $csvWriter->addRow($row);

            // Flush to STDOUT every X rows to avoid memory exhaustion.
            if ($lineCount  % MAX_ROWS_BUFFER === 0) {
                echo $csvWriter->getCSV(true);
                unset($csvWriter);
                $csvWriter = new CSVWriter();
            }

            ++$lineCount;
        }
    }
    echo $csvWriter->getCSV($lineCount >= MAX_ROWS_BUFFER);

}

main($argv);
