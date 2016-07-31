<?php

function debugmode($mode = 'query')
{
    $GLOBALS['debugmode'] = $mode;
}

function is_debugmode($mode = 'query')
{
    return (!empty($GLOBALS['debugmode']) && ($GLOBALS['debugmode'] == $mode));
}
