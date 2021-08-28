<?php

namespace App\Command\Service;

use DOMDocument;
use Symfony\Component\HttpKernel\KernelInterface;

class SilenceService
{
    private KernelInterface $appKernel;
    private string $sourceXmlFilePath;
    private float $chapterTimeout;
    private float $partTimeout;
    private float $maxChapterDuration;

    public function __construct(KernelInterface $appKernel)
    {
        $this->appKernel = $appKernel;
    }

    public function setSourceXmlFilePath(string $sourceXmlFilePath): void
    {
        $this->sourceXmlFilePath = $sourceXmlFilePath;
    }

    public function setChapterTimoutParameters(float $chapterTimeout, float $partTimeout, float $maxChapterDuration): void
    {
        $this->chapterTimeout = $chapterTimeout;
        $this->partTimeout = $partTimeout;
        $this->maxChapterDuration = $maxChapterDuration;
    }

    public function getSourceFileContents(): string
    {
        $xmlFilePath = $this->sourceXmlFilePath;

        if (file_exists($xmlFilePath)) {
            return file_get_contents($xmlFilePath);
        }

        $filePath = $this->appKernel->getProjectDir() . '/data/xmlsource/' . $xmlFilePath;
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }

        throw new \Exception('Source XML file coudn\'t be found');
    }

    public function process(): array
    {
        $sourceFileContents = $this->getSourceFileContents();

        $doc = new DOMDocument();
        $doc->loadXML($sourceFileContents);
        $elements = $doc->getElementsByTagName('silence');
        $data = [];
        foreach ($elements as $element) {
            $from = $this->parseTimePointerString($element->getAttribute('from'));
            $until = $this->parseTimePointerString($element->getAttribute('until'));

            $duration = $this->getSilenceDuration($from, $until);

            $data[] = [
                'untilTimePointerString' => $element->getAttribute('until'),
                'from' => $from,
                'until' => $until,
                'fromInSeconds' => $this->getTimestampInSeconds($from),
                'untilInSeconds' => $this->getTimestampInSeconds($until),
                'pauseDuration' => $duration,
            ];
        }

        $processingArray = [];
        $chapterNum = 1;
        $partNum = 1;
        $processItem = [
            'offset' => 'PT0S',
            'ossetInSeconds' => 0,
            'chapterNum' => $chapterNum,
            'partNum' => $partNum,
            'duration' => 0,
            'itemToOmit' => false,
        ];

        $currentChapterDuration = 0;
        foreach ($data as $srcItem) {
            $duration = $srcItem['fromInSeconds'] - $processItem['ossetInSeconds'];
            $currentChapterDuration += $duration;
            $processItem['duration'] = $duration;
            $processItem['totalChapterDuration'] = $currentChapterDuration;

            if ($srcItem['pauseDuration'] >= $this->chapterTimeout) {
                $chapterNum++;
                if ($partNum === 1) {
                    $processItem['partNum'] = null;
                }
                $partNum = 1;
                $currentChapterDuration = 0;
            } elseif (
                ($srcItem['pauseDuration'] >= $this->partTimeout) &&
                ($srcItem['pauseDuration'] < $this->chapterTimeout)
            ) {
                $partNum++;
            } else {
                $processItem['itemToOmit'] = true;
            }
            $processingArray[] = $processItem;

            $processItem = [
                'offset' => $srcItem['untilTimePointerString'],
                'ossetInSeconds' => $srcItem['untilInSeconds'],
                'chapterNum' => $chapterNum,
                'partNum' => $partNum,
                'duration' => 0,
                'itemToOmit' => false,
            ];
        }

        $currentChapterNum = 1;
        foreach ($processingArray as $index => $processItem) {
            if ($currentChapterNum !== $processItem['chapterNum']) {
                if (($index !== 0) && $processingArray[($index - 1)]['totalChapterDuration'] < $this->maxChapterDuration) {
                    for ($i = ($index - 1); $i >= 0; $i--) {
                        if (
                            ($processingArray[$i]['chapterNum'] === $currentChapterNum) &&
                            (
                                ($processingArray[$i]['partNum'] === 1) ||
                                ($processingArray[$i]['partNum'] === null)
                            )
                        ) {
                            $processingArray[$i]['partNum'] = null;
                            $processingArray[$i]['itemToOmit'] = false;
                        } elseif (
                            ($processingArray[$i]['chapterNum'] === $currentChapterNum) &&
                            ($processingArray[$i]['partNum'] !== 1)
                        ) {
                            $processingArray[$i]['itemToOmit'] = true;
                        } elseif ($processingArray[$i]['chapterNum'] !== $currentChapterNum) {
                            break;
                        }
                    }
                }
            }
            $currentChapterNum = $processItem['chapterNum'];
        }

        $output = [];
        foreach ($processingArray as $processItem) {
            if ($processItem['itemToOmit'] === true) {
                continue;
            }

            $title = 'Chapter ' . $processItem['chapterNum'];
            if ($processItem['partNum']) {
                $title .= ', part ' . $processItem['partNum'];
            }
            $output[] = [
                'title' => $title,
                'offset' => $processItem['offset'],
            ];
        }

        return $output;
    }

    private function parseTimePointerString(string $timePointer): array
    {
        $timePointer = ltrim($timePointer, 'PT');
        $hoursPos = stripos($timePointer, 'H');
        $hours = 0;
        if ($hoursPos !== false) {
            $hours = (int)substr($timePointer, 0, $hoursPos);
        }

        $minutesPos = stripos($timePointer, 'M');
        $minutes = 0;
        if ($minutesPos !== false) {
            $minutes = (int)substr($timePointer, ($hoursPos + 1), ($minutesPos - $hoursPos - 1));
        }

        $secondsPos = stripos($timePointer, 'S');
        $seconds = 0;
        if ($secondsPos !== false) {
            $seconds = (float)substr($timePointer, ($minutesPos + 1), ($secondsPos - $minutesPos - 1));
        }

        return [
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
        ];
    }

    private function getSilenceDuration(array $from, array $until): float
    {
        $hours = $until['hours'] - $from['hours'];
        $minutes = $until['minutes'] - $from['minutes'];
        $seconds = $until['seconds'] - $from['seconds'];

        return ($hours * 60 * 60) + ($minutes * 60) + $seconds;
    }

    private function getTimestampInSeconds(array $timestamp): float
    {
        $hours = $timestamp['hours'];
        $minutes = $timestamp['minutes'];
        $seconds = $timestamp['seconds'];

        return ($hours * 60 * 60) + ($minutes * 60) + $seconds;
    }

    public function out(string $jsonOutput): void
    {
        $filePath = $this->appKernel->getProjectDir() . '/data/jsonoutput/out.json';
        file_put_contents($filePath, $jsonOutput);
    }
}
