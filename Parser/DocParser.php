<?php
/*
 * Copyright (c) 2017, whatwedo GmbH
 * All rights reserved
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace whatwedo\DocBundle\Parser;

use Knp\Bundle\MarkdownBundle\Parser\Preset\Medium;

/**
 * Class DocParser
 * @package whatwedo\DocBundle\Parser
 */
class DocParser extends Medium
{

    /**
     * Callback for setext headers
     * @param  array $matches
     * @return string
     */
    protected function _doHeaders_callback_setext($matches)
    {
        if ($matches[3] == '-' && preg_match('{^- }', $matches[1])) {
            return $matches[0];
        }

        $level = $matches[3]{0} == '=' ? 1 : 2;
        $level += 3;
        if ($level > 6) {
            $level = 6;
        }

        $defaultId = is_callable($this->header_id_func) ? call_user_func($this->header_id_func, $matches[1]) : null;

        $attr  = $this->doExtraAttributes("h$level", $dummy = & $matches[2], $defaultId);
        $block = "<h$level$attr>" . $this->runSpanGamut($matches[1]) . "</h$level>";
        return "\n" . $this->hashBlock($block) . "\n\n";
    }

    /**
     * Callback for atx headers
     * @param  array $matches
     * @return string
     */
    protected function _doHeaders_callback_atx($matches)
    {
        $level = strlen($matches[1]);
        $level += 3;
        if ($level > 6) {
            $level = 6;
        }

        $defaultId = is_callable($this->header_id_func) ? call_user_func($this->header_id_func, $matches[2]) : null;
        $attr  = $this->doExtraAttributes("h$level", $dummy = & $matches[3], $defaultId);
        $block = "<h$level$attr>" . $this->runSpanGamut($matches[2]) . "</h$level>";
        return "\n" . $this->hashBlock($block) . "\n\n";
    }
}
