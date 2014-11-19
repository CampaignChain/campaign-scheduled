<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Campaign\ScheduledCampaignBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class PlanController extends Controller
{
    public function timelineAction(){
        return $this->render(
            'CampaignChainCoreBundle:Plan/Timeline:index.html.twig',
            array(
                'page_title' => 'Timeline',
                'gantt_tasks' => $this->get('campaignchain.core.model.dhtmlxgantt')->getTasks(),
                'gantt_toolbar_status' => 'default',
                'campaignchain_style' => $this->container->getParameter('campaignchain_core')['style'],
                'path_embedded' => $this->generateUrl('campaignchain_campaign_scheduled_plan_timeline'),
                'path_fullscreen' =>  $this->generateUrl('campaignchain_campaign_scheduled_plan_timeline_fullscreen'),
            ));
    }

    public function timelineFullScreenAction(){
        return $this->render(
            'CampaignChainCoreBundle:Plan/Timeline:fullscreen.html.twig',
            array(
                'page_title' => 'Timeline',
                'gantt_tasks' => $this->get('campaignchain.core.model.dhtmlxgantt')->getTasks(),
                'gantt_toolbar_status' => 'fullscreen',
                'campaignchain_style' => $this->container->getParameter('campaignchain_core')['style'],
                'path_embedded' => $this->generateUrl('campaignchain_campaign_scheduled_plan_timeline'),
                'path_fullscreen' =>  $this->generateUrl('campaignchain_campaign_scheduled_plan_timeline_fullscreen'),
            ));
    }

    public function calendarAction(){
        return $this->render(
            'CampaignChainCoreBundle:Plan/Calendar:index.html.twig',
            array(
                'page_title' => 'Calendar',
                'events' => $this->get('campaignchain.core.model.fullcalendar')->getEvents(),
            ));
    }
}