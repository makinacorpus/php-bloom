<?php

namespace MakinaCorpus\Bloom\Tests;

use MakinaCorpus\Bloom\BloomFilter;

class BloomFitlerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (getenv('BLOOM_TEST_DISABLE')) {
            $this->markTestSkipped();
        }
    }

    private function generateRandomString($length = 32)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public function testBasicFunctionnality()
    {
        $strings      = [];
        $maxSize      = (float)getenv("BLOOM_SIZE");
        $probability  = (float)getenv("BLOOM_PROBABILITY");
        $filter       = new BloomFilter(null, $maxSize, $probability);

        for ($i = 0; $i < $maxSize; ++$i) {
            $strings[$this->generateRandomString()] = rand(0, 1);
        }

        // Set everything in the hash
        foreach ($strings as $string => $isIn) {
            if ($isIn) {
                $filter->set($string);
            }
        }

        $countIn = 0;
        $countMiss = 0;

        // Test everything
        foreach ($strings as $string => $isIn) {

            $result = $filter->check($string);

            if ($isIn) {
                ++$countIn;

                if (!$result) {
                    $this->fail("False negative is not possible");
                }

            } else {
                if ($result) {
                    ++$countMiss;
                }
            }
        }

        echo "\nmax size: ", $maxSize, "\nin: ", $countIn, "\nmiss: ", $countMiss, "\n";

        $this->assertTrue($countMiss / $maxSize < $probability);
    }
}
