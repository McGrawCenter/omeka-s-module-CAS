<?php

/*
 * Copyright 2020 BibLibre
 *
 * This file is part of CAS.
 *
 * CAS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * CAS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CAS.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace CAS\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class CasLoginUrl extends AbstractHelper
{
    public function __invoke(array $options = [])
    {
        $view = $this->getView();

        $query = [];
        if (!empty($options['redirect_url'])) {
            $query['redirect_url'] = $options['redirect_url'];
        }

        return $view->url('cas/login', [], ['force_canonical' => true, 'query' => $query]);
    }
}
