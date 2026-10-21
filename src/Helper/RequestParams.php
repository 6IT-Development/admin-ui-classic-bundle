<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license GPLv3 and PCL
 */

namespace Pimcore\Bundle\AdminBundle\Helper;

use Symfony\Component\HttpFoundation\Request;

/**
 * Replacement for Request::get() (deprecated in Symfony 7.4, removed in 8.0) for call sites where the
 * parameter source is unclear (write routes, where ExtJS may send a param either in the URL query or in the body).
 *
 * TODO: Resolve each call site to the explicit bag ($request->attributes, ->query or ->request) by checking
 *       how the frontend sends the parameter, then remove this class.
 *
 * @internal
 *
 * @deprecated since 1.7, use $request->attributes, $request->query or $request->request directly
 */
final class RequestParams
{
    /**
     * Same lookup order as Request::get(): attributes, query, request. Like Request::get(), it may return arrays.
     */
    public static function get(Request $request, string $key, mixed $default = null): mixed
    {
        trigger_deprecation(
            'six-it/admin-ui-classic-bundle',
            '1.7',
            'Reading request parameter "%s" via %s::get() is deprecated, use $request->attributes, $request->query or $request->request directly.',
            $key,
            self::class
        );

        if ($request !== $result = $request->attributes->get($key, $request)) {
            return $result;
        }

        if ($request->query->has($key)) {
            return $request->query->all()[$key];
        }

        if ($request->request->has($key)) {
            return $request->request->all()[$key];
        }

        return $default;
    }
}
