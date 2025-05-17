<?php
/**
 * LyraIndia Magento2 Module using \Magento\Payment\Model\Method\AbstractMethod
 * Copyright (C) 2025 Lyra.com
 * 
 * This file is part of Lyra/LyraIndia.
 * 
 * Lyra/LyraIndia is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Lyra\LyraIndia\Model\Config\Source;

/**
 * Option source for Integration types
 * 
 * inline    : Popup type
 * standard  : Redirecting type
 * 
 */
class IntegrationType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'standard', 'label' => __('Standard - (Redirect)')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return ['standard' => __('Standard - (Redirect)')];
    }
}
