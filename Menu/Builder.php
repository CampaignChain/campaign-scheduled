<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CampaignChain\Campaign\ScheduledCampaignBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAware;

class Builder extends ContainerAware
{
    public function planListTab(FactoryInterface $factory, array $options)
    {
        $menu = $factory->createItem('root');

        $menu->addChild('Timeline', array('route' => 'campaignchain_campaign_scheduled_plan_timeline'));
        $menu->addChild('Calendar', array('route' => 'campaignchain_campaign_scheduled_plan_calendar'));

        return $menu;
    }

    public function detailsTab(FactoryInterface $factory, array $options)
    {
        $id = $this->container->get('request')->get('id');

        $menu = $factory->createItem('root');

        $menu->addChild(
            'List',
            array(
                'route' => 'campaignchain_campaign_scheduled_plan_timeline'
            )
        );
        $menu->addChild(
            'Edit',
            array(
                'route' => 'campaignchain_campaign_scheduled_edit',
                'routeParameters' => array(
                    'id' => $id
                )
            )
        );
        $menu->addChild(
            'Timeline',
            array(
                'route' => 'campaignchain_campaign_scheduled_plan_timeline_detail',
                'routeParameters' => array(
                    'id' => $id
                )
            )
        );

        return $menu;
    }
}