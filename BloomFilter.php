<?php

namespace MakinaCorpus\Bloom;

/**
 * This class relies on the GMP extension to be loaded in PHP
 *
 * All credits to the original author; only a few code style changed a minor
 * performance optimizations have been done.
 *
 * This implementation is serializable, you may store it as-is and load it
 * without any problems.
 *
 * @author Sherif Ramadan
 * @link http://phpden.info/Bloom-filters-in-PHP
 */
final class BloomFilter implements \Serializable
{
    private $maxSize;
    private $probability;
    private $space;
    private $hashes;
    private $filter;
    private $hashAlgos;
    private $empty = true;
    private $useBcMath = false;

    /**
     * Default constructor
     *
     * @param int $maxSize
     *   Maximum number of elements you wish to store.
     * @param real $probability
     *   False positive probability you wish to achieve.
     */
    public function __construct($maxSize = 64, $probability = 0.001)
    {
        $this->maxSize = $maxSize;
        $this->probability = $probability;

        $this->init();

        $this->filter = str_repeat("\0", ceil($this->space / 8));
    }

    private function init()
    {
        $this->space      = $this->calculateSpace($this->maxSize, $this->probability);
        $this->hashes     = $this->calculateHashFunctions($this->maxSize, $this->space);
        $this->hashAlgos  = $this->getHashAlgos();

        if ($this->hashes > $this->numHashFunctionsAvailable($this->hashAlgos)) {
            throw new \LogicException("Can't initialize filter with available hash functions");
        }

        if (!function_exists('gmp_init')) {
            if (!function_exists('bcmod')) {
                throw new \LogicException("Can't initialize filter if you don't have any of the 'gmp' or 'bcmath' extension (gmp is faster)");
            }
            $this->useBcMath = true;
        }
    }

    private function getHashAlgos()
    {
        return hash_algos();
    }

    private function calculateSpace($maxSize, $probability)
    {
        return (int)ceil(($maxSize * (log($probability)) / (pow(log(2), 2))) * -1);
    }

    private function calculateHashFunctions($maxSize, $space)
    {
        return (int)ceil($space / $maxSize * log(2));
    }

    private function numHashFunctionsAvailable($hashAlgos)
    {
        $num = 0;

        foreach ($hashAlgos as $algo) {
            $num += count(unpack('J*', hash($algo, 'bloom', true)));
        }

        return $num;
    }

    private function hash($element)
    {
        $hashes = [];

        foreach ($this->hashAlgos as $algo) {
            foreach (unpack('P*', hash($algo, $element, true)) as $hash) {
                if ($this->useBcMath) {
                    $hashes[] = bcmod(sprintf("%u", $hash), $this->space);
                } else {
                    $hash = gmp_init(sprintf("%u", $hash));
                    $hashes[] = ($hash % $this->space);
                }
                if (count($hashes) >= $this->hashes) {
                    break 2;
                }
            }
        }

        return $hashes;
    }

    /**
     * Set element in the filter
     *
     * @param mixed $element
     */
    public function set($element)
    {
        if (!is_scalar($element)) {
            $element = serialize($element);
        }

        $hashes = $this->hash($element);

        foreach ($hashes as $hash) {
            $offset = (int)floor($hash / 8);
            $bit = (int)($hash % 8);
            $this->filter[$offset] = chr(ord($this->filter[$offset]) | (pow(2, $bit)));
        }

        $this->empty = false;
    }

    /**
     * Is element in the hash
     *
     * @param mixed $element
     *
     * @return boolean
     *   Beware that a strict false means strict false, while a strict true
     *   means "probably with a X% probably" where X is the value you built
     *   the filter with.
     */
    public function check($element)
    {
        if (!is_scalar($element)) {
            $element = serialize($element);
        }

        $hashes = $this->hash($element);

        foreach ($hashes as $hash) {

            $offset = (int)floor($hash / 8);
            $bit = (int)($hash % 8);

            if (!(ord($this->filter[$offset]) & (pow(2, $bit)))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Is this instance empty
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return $this->empty;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return implode(',', [$this->maxSize, $this->probability, base64_encode($this->filter)]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->maxSize, $this->probability, $this->filter) = explode(',', $serialized, 3);
        $this->filter = base64_decode($this->filter);

        $this->init();
        $this->empty = false;
    }
}
