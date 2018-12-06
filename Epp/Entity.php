<?php

namespace Domreg\Epp;

class Entity {

    public static function make() {
        return new static();
    }

    /**
     * Initializes the entity with data from an array.
     *
     * @param  array $data An array with data to assign to this entity
     * @return static
     */
    public function fromArray($data = []) {
        // Initialize from provided data
        foreach ($data as $i => $value) {
            $this->{$i} = $value;
        }
    }

    /**
     * Converts entity to an array
     *
     * @return array
     */
    public function toArray() {
        $to_array = function ($self) {
            return get_object_vars($self);
        };
        $array = $to_array($this);
        foreach ($array as $i => $value) {
            // Unset null fields
            if (is_null($value)) {
                unset($array[$i]);
                continue;
            }
        }
        return $array;
    }

}
