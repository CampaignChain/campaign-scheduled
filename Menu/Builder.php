<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
}