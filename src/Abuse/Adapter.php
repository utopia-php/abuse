<?php

namespace Utopia\Abuse;

interface Adapter
{
    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit
     *
     * @return bool
     */
    public function check();
}