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

namespace whatwedo\DocBundle\Manager;

use Knp\Bundle\MarkdownBundle\Parser\MarkdownParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Router;
use whatwedo\DocBundle\Exception\DuplicateRouteException;
use whatwedo\DocBundle\Model\DirectoryListingDocument;
use whatwedo\DocBundle\Model\Document;
use whatwedo\DocBundle\Model\MarkdownDocument;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

/**
 * Class DocManager
 * @package whatwedo\DocBundle\Manager
 */
class DocManager
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var MarkdownParser
     */
    protected $markdownParser;

    /**
     * @var Breadcrumbs
     */
    protected $breadcrumbs;

    /**
     * @var array
     */
    protected $config;

    /**
     * DocManager constructor.
     * @param ContainerInterface $container
     * @param Router $router
     * @param MarkdownParser $markdownParser
     * @param Breadcrumbs $breadcrumbs
     *
     * Injecting container due to https://github.com/symfony/symfony/issues/2347#issuecomment-2838590
     */
    public function __construct(
        ContainerInterface $container,
        Router $router,
        MarkdownParser $markdownParser,
        Breadcrumbs $breadcrumbs
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->markdownParser = $markdownParser;
        $this->breadcrumbs = $breadcrumbs;
    }

    /**
     * @param string $path
     * @return Response
     */
    public function getDocumentResponse($path)
    {
        $path = $this->sanitizePath($path);
        $absolutePath = $this->getAbsoluteFilesystemPath($path);
        $this->buildPageBreadcrumbs($path);

        if (!is_dir($absolutePath) && file_exists($absolutePath)) {
            return $this->getFileResponse($absolutePath);
        } elseif (file_exists($absolutePath.'.md')) {
            return $this->getMarkdownResponse($absolutePath.'.md');
        } elseif (is_dir($absolutePath) && file_exists($absolutePath.'/index.md')) {
            return $this->getMarkdownResponse($absolutePath.'/index.md');
        } elseif (is_dir($absolutePath)) {
            return $this->getDirectoryListingResponse($absolutePath);
        }
        throw new NotFoundHttpException('Seite nicht gefunden');
    }

    /**
     * @param string $query
     * @return Response
     */
    public function getSearchResponse($query)
    {
        $this->buildSearchBreadcrumbs();
        $documents = array_slice($this->getMatchingDocuments($query), 0, 10);
        $html = $this->container->get('twig')->render('whatwedoDocBundle:Doc:search.html.twig', array(
            'documents' => $documents,
            'query' => $query,
        ));
        return new Response($html);
    }

    /**
     * @param string $route
     * @return string
     * @throws DuplicateRouteException
     */
    public function getDocUriByRoute($route)
    {
        foreach ($this->getRouteMapping() as $key => $item) {
            if (preg_match($key, $route)) {
                return $this->router->generate('whatwedo_doc_doc_page', ['path' => $this->getRouteMapping()[$key]]);
            }
        }
        return null;
    }

    /**
     * @param Request $request
     * @return string
     */
    public function getDocUriByRequest(Request $request)
    {
        return $this->getDocUriByRoute($request->get('_route'));
    }

    /**
     * @param string $query
     * @return MarkdownDocument[]
     */
    public function getMatchingDocuments($query)
    {
        $documents = $this->getMarkdownDocuments();
        foreach ($documents as $key => $document) {
            if (stripos($document->getContent(), $query) === false && stripos($document->getTitle(), $query) === false) {
                unset($documents[$key]);
            }
        }
        return $documents;
    }

    /**
     * @return MarkdownDocument[]
     */
    protected function getMarkdownDocuments()
    {
        $documents = [];
        $it = new RecursiveDirectoryIterator($this->getDocRoot());
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($it) as $file) {
            if (is_dir($file->getPathname()) || !preg_match('/\.md$/', $file->getFilename())) {
                continue;
            }
            $documents[] = $this->getMarkdownDocument($file->getPathname());
        }
        return $documents;
    }

    /**
     * Remove trailing and leading slashes and also .md file extension from the given path.
     *
     * @param string $path path to sanitize
     * @return string sanitized path
     */
    protected function sanitizePath($path)
    {
        $path = preg_replace('/\.md/i', '', $path);
        $path = trim($path, '/');
        return $path;
    }

    /**
     * @param string $absolutePath
     * @return BinaryFileResponse
     */
    protected function getFileResponse($absolutePath)
    {
        return new BinaryFileResponse($absolutePath);
    }

    /**
     * Get listing directory as represented as markdown
     *
     * @param string $absolutePath
     * @param bool $docRoot
     * @return Response
     */
    protected function getDirectoryListingResponse($absolutePath, $docRoot = false)
    {
        $document = $this->getDirectoryListingDocument($absolutePath);

        $html = $this->container->get('twig')->render('whatwedoDocBundle:Doc:page.html.twig', [
            'document' => $document,
        ]);

        return new Response($html);
    }

    /**
     * @param string $absolutePath
     * @return DirectoryListingDocument
     */
    protected function getDirectoryListingDocument($absolutePath)
    {
        $document = new DirectoryListingDocument();
        $files = scandir($absolutePath, SCANDIR_SORT_ASCENDING);
        foreach ($files as $file) {
            $filePath = $absolutePath.'/'.$file;
            if (preg_match('/\.md$/', $file) || (is_dir($filePath) && !in_array($file, ['.', '..']))) {
                $page = new Document();
                $page
                    ->setAbsolutePath($filePath)
                    ->setTitle($this->getTitle($filePath))
                    ->setPath($this->getPath($filePath));
                $document->addPage($page);
            }
        }

        $markdown = $this->container->get('twig')->render('whatwedoDocBundle:Doc:directory-listing.md.twig', [
            'document' => $document,
        ]);
        $document->setContent($markdown)
            ->setTitle($this->getTitle($absolutePath));

        return $document;
    }

    /**
     * @param string $absolutePath
     * @return Response
     */
    protected function getMarkdownResponse($absolutePath)
    {
        $document = $this->getMarkdownDocument($absolutePath);

        $html = $this->container->get('twig')->render('whatwedoDocBundle:Doc:page.html.twig', array(
            'document' => $document,
        ));

        return new Response($html);
    }

    /**
     * @param string $absolutePath
     * @return MarkdownDocument
     */
    protected function getMarkdownDocument($absolutePath)
    {
        $document = new MarkdownDocument();
        $document
            ->setContent(file_get_contents($absolutePath))
            ->setTitle($this->getTitle($absolutePath))
            ->setAbsolutePath($absolutePath)
            ->setPath($this->getPath($absolutePath));
        return $document;
    }

    /**
    * Get absolute filesystem path to file representing the given path
    *
    * @param string $path path from URL
    * @return string absolute file system path
    */
    protected function getAbsoluteFilesystemPath($path)
    {
        return $this->getDocRoot().'/'.$path;
    }

    /**
     * @param string $path
     */
    protected function buildPageBreadcrumbs($path)
    {
        // Add default
        $this->breadcrumbs->addItem($this->config['home']['title'], $this->router->generate($this->config['home']['route']));
        $this->breadcrumbs->addItem('Dokumentation', $this->router->generate('whatwedo_doc_doc_page'));

        // Add pages
        $splitArray = explode('/', $path);
        $itemPath = null;
        foreach ($splitArray as $item) {
            if (is_null($itemPath)) {
                $itemPath = $item;
            } else {
                $itemPath .= '/'.$item;
            }
            $this->breadcrumbs->addItem($this->getTitle($this->getTitle($itemPath)), $this->router->generate('whatwedo_doc_doc_page', [
                'path' => $itemPath
            ]));
        }
    }

    /**
     *
     */
    protected function buildSearchBreadCrumbs()
    {
        $this->breadcrumbs->addItem($this->config['home']['title'], $this->router->generate($this->config['home']['route']));
        $this->breadcrumbs->addItem('Dokumentation', $this->router->generate('whatwedo_doc_doc_page'));
        $this->breadcrumbs->addItem('Suche', $this->router->generate('whatwedo_doc_doc_search'));
    }

    /**
     * @param string $absolutePath
     * @return string
     */
    protected function getPath($absolutePath)
    {
        $count = 1;
        $path = str_replace($this->getDocRoot(), '', $absolutePath, $count);
        $path = $this->sanitizePath($path);
        return $this->router->generate('whatwedo_doc_doc_page', ['path' => $path]);
    }

    /**
     * @param string $absolutePath
     * @return string
     */
    protected function getTitle($absolutePath)
    {
        // Special cases
        if (rtrim($this->getDocRoot(), '/') == rtrim($absolutePath, '/')) {
            return 'Index';
        } elseif (rtrim($this->getDocRoot(), '/').'/index.md' == $absolutePath) {
            return 'Index';
        } elseif ($absolutePath == '') {
            return 'Index';
        }
        if (preg_match('/\/index.md/', $absolutePath)) {
            $title = basename(preg_replace('/\/index.md$/', '', $absolutePath), '.md');
            ;
        } else {
            $title = basename($absolutePath, '.md');
        }
        $title = preg_replace('/\.md/i', '', $title);
        return str_replace('_', ' ', $title);
    }

    /**
     * @return string
     */
    public function getDocRoot()
    {
        return $this->config['doc_root'];
    }

    /**
     * @return array
     */
    public function getRouteMapping()
    {
        return $this->config['route_mapping'];
    }

    /**
     * @param array $config
     * @return DocManager
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }
}
