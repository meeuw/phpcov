<?php declare(strict_types=1);
/*
 * This file is part of phpcov.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\PHPCOV;

use const DIRECTORY_SEPARATOR;
use function assert;
use function file_get_contents;
use function is_array;
use function substr;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser as DiffParser;

final class PatchCoverage
{
    public function execute(string $coverageFile, string $patchFile, string $pathPrefix, string $mapFrom, string $mapTo): array
    {
        $result = [
            'numChangedLinesThatAreExecutable' => 0,
            'numChangedLinesThatWereExecuted'  => 0,
            'changedLinesThatWereNotExecuted'  => [],
        ];

        if (substr($pathPrefix, -1, 1) !== DIRECTORY_SEPARATOR) {
            $pathPrefix .= DIRECTORY_SEPARATOR;
        }

        $coverage = $this->loadCodeCoverage($coverageFile, $mapFrom, $mapTo);

        assert($coverage instanceof CodeCoverage);

        $coverage = $coverage->getData()->lineCoverage();
        $patch    = (new DiffParser)->parse(file_get_contents($patchFile));
        $changes  = [];

        foreach ($patch as $diff) {
            $file           = substr($diff->getTo(), 2);
            $changes[$file] = [];

            foreach ($diff->getChunks() as $chunk) {
                $lineNr = $chunk->getEnd();

                foreach ($chunk->getLines() as $line) {
                    if ($line->getType() === Line::ADDED) {
                        $changes[$file][] = $lineNr;
                    }

                    if ($line->getType() !== Line::REMOVED) {
                        $lineNr++;
                    }
                }
            }
        }

        foreach ($changes as $file => $lines) {
            $key = $pathPrefix . $file;

            foreach ($lines as $line) {
                if (isset($coverage[$key][$line]) &&
                    is_array($coverage[$key][$line])) {
                    $result['numChangedLinesThatAreExecutable']++;

                    if (empty($coverage[$key][$line])) {
                        if (!isset($result['changedLinesThatWereNotExecuted'][$file])) {
                            $result['changedLinesThatWereNotExecuted'][$file] = [];
                        }

                        $result['changedLinesThatWereNotExecuted'][$file][] = $line;
                    } else {
                        $result['numChangedLinesThatWereExecuted']++;
                    }
                }
            }
        }

        return $result;
    }
}
