<?php

namespace Bolt\Extension\Bolt\BoltForms\Config\Section;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * File upload configuration.
 *
 * Copyright (c) 2014-2016 Gawain Lynch
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Gawain Lynch <gawain.lynch@gmail.com>
 * @copyright Copyright (c) 2014-2016, Gawain Lynch
 * @license   http://opensource.org/licenses/GPL-3.0 GNU Public License 3.0
 */
class Uploads extends ParameterBag
{
    /**
     * Constructor.
     */
    public function __construct(array $parameter)
    {
        parent::__construct($parameter);
    }

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->get('enabled', false);
    }

    /**
     * @param boolean $enabled
     *
     * @return Uploads
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseDirectory()
    {
        return $this->get('base_directory');
    }

    /**
     * @param string $baseDirectory
     *
     * @return Uploads
     */
    public function setBaseDirectory($baseDirectory)
    {
        $this->set('base_directory', $baseDirectory);

        return $this;
    }

    /**
     * @return string
     */
    public function getFilenameHandling()
    {
        return $this->get('filename_handling', 'suffix');
    }

    /**
     * @param string $filenameHandling
     *
     * @return Uploads
     */
    public function setFilenameHandling($filenameHandling)
    {
        $this->set('filename_handling', $filenameHandling);

        return $this;
    }

    /**
     * @return string
     */
    public function getManagementController()
    {
        return $this->get('management_controller', false);
    }

    /**
     * @param string $managementController
     *
     * @return Uploads
     */
    public function setManagementController($managementController)
    {
        $this->set('management_controller', $managementController);

        return $this;
    }
}
