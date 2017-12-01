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

namespace whatwedo\DocBundle\Twig;

use Symfony\Component\HttpFoundation\Request;
use whatwedo\DocBundle\Manager\DocManager;

/**
 * Class DocExtension
 * @package whatwedo\DocBundle\Twig
 */
class DocExtension extends \Twig_Extension
{

    /**
     * @var DocManager
     */
    protected $docManager;

    /**
     * DocExtension constructor.
     * @param DocManager $docManager
     */
    public function __construct(DocManager $docManager)
    {
        $this->docManager = $docManager;
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_Function('doc_uri', [$this, 'getDocUri']),
        ];
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_Filter('doc_excerpt', [$this, 'getDocExcerpt']),
        ];
    }

    /**
     * @param Request $request
     * @return string
     */
    public function getDocUri(Request $request)
    {
        return $this->docManager->getDocUriByRequest($request);
    }

    /**
     * @param $content
     * @return null|string
     */
    public function getDocExcerpt($content)
    {
        $wordsNumber = 160;
        $content = strip_tags($content);
        $words = str_word_count($content, 1);
        if (!empty($content) && (count($words) > $wordsNumber)) {
            $output = [];
            for ($wordsCounter = 0; $wordsCounter < $wordsNumber; $wordsCounter++) {
                $output[] = $words[$wordsCounter];
            }
            return implode(' ', $output);
        }
        return null;
    }
}
