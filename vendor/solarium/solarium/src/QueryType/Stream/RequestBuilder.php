<?php

namespace Solarium\QueryType\Stream;

use Solarium\Core\Client\Request;
use Solarium\Core\Query\AbstractQuery;
use Solarium\Core\Query\QueryInterface;
use Solarium\Core\Query\RequestBuilderInterface;

/**
 * Build a stream request.
 */
class RequestBuilder implements RequestBuilderInterface
{
    /**
     * Build request for a stream query.
     *
     * @param QueryInterface|Query $query
     *
     * @return Request
     */
    public function build(AbstractQuery $query): Request
    {
        $charset = $query->getInputEncoding('ie') ?? 'utf-8';

        $request = new Request();
        $request->setHandler($query->getHandler());
        $request->addParam('expr', $query->getExpression());
        $request->addParams($query->getParams());
        $request->addHeader('Content-Type: text/plain; charset='.$charset);

        return $request;
    }
}
