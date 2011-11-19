<?php

/**
 * Pointless file
 * @package TaskServer
 * @version 0.1
 * @author Florin Patan
 */

function termsParser($terms)
{
    function space_replacer($matches)
    {
        return str_replace(" ", "##__ShPACE__##", $matches[0]);
    }

    function space_replacer2($value)
    {
        return str_replace("##__ShPACE__##", " ", $value);
    }

    $terms = strtolower($terms);
    if (strpos($terms, " ") !== false) {
        $search = array(
            "@\s+or\s+@",
            "@\s+and\s+@",
            "@'@",
            "@\s+@");
        $replace = array(
            " ",
            " ",
            '"',
            " ");
        $terms = preg_replace($search, $replace, $terms);
        $terms = preg_replace_callback("@\"(\w+\s*)+\"@", "space_replacer", $terms);
        $terms = explode(" ", $terms);
        $quote = array();
        $minus = array();
        $cnt = count($terms);
        for ($i = 0; $i < $cnt; $i++)
        {
            $stletter = $terms[$i];
            if ($stletter[0] == '"') {
                $t = array_splice($terms, $i, 1);
                $quote[] = $t[0];
                $i--;
                $cnt--;
            }
            elseif ($stletter[0] == "-")
            {
                $t = array_splice($terms, $i, 1);
                $minus[] = $t[0];
                $i--;
                $cnt--;
            }
        }

        $terms = "((" . implode(" OR ", $terms) . ")";
        if (count($quote) > 0) {
            $quote = array_map("space_replacer2", $quote);
            $terms .= " AND (" . implode(" AND ", $quote) . ")";
        }

        if (count($minus) > 0) {
            $minus = array_map("space_replacer2", $minus);
            $terms .= " AND (" . implode(" AND ", $minus) . ")";
        }

        $terms .= ")";
    }
    else
    {
        $terms = "(" . $terms . ")";
    }

    return $terms;
}

$terms = "blonde   fuck 18 \"in the ass\" -asian old -'suck ing' 'duck'";
//$terms = "blonde fuck";
echo termsParser($terms);
