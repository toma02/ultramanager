<?php

namespace FluentFormPro\Integrations\UserRegistration;

use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

trait Getter
{
    /**
     * Get the username value from the form data
     * by formatting the shortcode properly.
     */
    public function getUsername($username, $data)
    {
        $username = str_replace(
            ['[', ']'], 
            ['.', ''], 
            $username
        );

        return ArrayHelper::get($data, $username);
    }
}
