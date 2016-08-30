<?php

namespace MakinaCorpus\Bloom;

/**
 * This class relies on the GMP extension to be loaded in PHP
 *
 * All credits to the original author; only a few code style changed a minor
 * performance optimizations have been done.
 *
 * @todo for storage purpose, we need to have the hash algorithm usage
 *   predictible, so we may use the value on potentially differerent
 *   PHP environments
 *
 * @author Sherif Ramadan
 * @link http://phpden.info/Bloom-filters-in-PHP
 */
final class BloomFilter
{
    private $maxSize;
    private $probability;
    private $space;
    private $hashes;
    private $filter;
    private $empty = true;

    /**
     * Default constructor
     *
     * @param string $value
     *   Any already computed value, this value MUST have been computed using
     *   the same PHP environment (same hash functions), the same $maxSize and
     *   the same $probability parameters values.
     * @param int $maxSize
     *   Maximum number of elements you wish to store.
     * @param real $probability
     *   False positive probability you wish to achieve.
     */
    public function __construct($value = null, $maxSize = 64, $probability = 0.001)
    {
        $this->maxSize      = $maxSize;
        $this->probability  = $probability;
        $this->space        = $this->calculateSpace($this->maxSize, $this->probability);
        $this->hashes       = $this->calculateHashFunctions($this->maxSize, $this->space);

        if ($this->hashes > $this->numHashFunctionsAvailable()) {
            throw new \LogicException("Can't initialize filter with available hash functions");
        }

        $size = ceil($this->space / 8);
        $this->filter = str_repeat("\0", $size);

        if (null !== $value && is_string($value)) {

            $current = strlen($value);
            if ($size < $current) {
                throw new \InvalidArgumentException("given filter value is too long for the current filter");
            }

            $this->filter = substr_replace($this->filter, $value, 0, $current);
            $this->empty = false;
        }
    }

    private function calculateSpace($maxSize, $probability)
    {
        return (int)ceil(($maxSize * (log($probability)) / (log(2) ** 2)) * -1);
    }

    private function calculateHashFunctions($maxSize, $space)
    {
        return (int)ceil($space / $maxSize * log(2));
    }

    private function numHashFunctionsAvailable()
    {
        $num = 0;

        foreach (hash_algos() as $algo) {
            $num += count(unpack('J*', hash($algo, 'bloom', true)));
        }

        return $num;
    }

    private function hash($element)
    {
        $hashes = [];

        foreach (hash_algos() as $algo) {
            foreach (unpack('P*', hash($algo, $element, true)) as $hash) {
                $hash = gmp_init(sprintf("%u", $hash));
                $hashes[] = ($hash % $this->space);
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
            $this->filter[$offset] = chr(ord($this->filter[$offset]) | (2 ** $bit));
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

            if (!(ord($this->filter[$offset]) & (2 ** $bit))) {
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
     * Get raw filter value, for storage
     *
     * @return string
     */
    public function __toString()
    {
        return $this->filter;
    }
}
