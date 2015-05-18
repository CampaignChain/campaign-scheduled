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

use CampaignChain\CoreBundle\Util\DateTimeUtil;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use CampaignChain\CoreBundle\Entity\Campaign;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Doctrine\ORM\EntityRepository;

class ScheduledCampaignController extends Controller
{
    const FORMAT_DATEINTERVAL = 'Years: %Y, months: %m, days: %d, hours: %h, minutes: %i, seconds: %s';
    const BUNDLE_NAME = 'campaignchain/campaign-scheduled';
    const MODULE_IDENTIFIER = 'campaignchain-scheduled';

    public function newAction(Request $request)
    {
        // create a campaign and give it some dummy data for this example
        $campaign = new Campaign();
        $campaign->setTimezone($this->get('session')->get('campaignchain.timezone'));
        $dateTimeZone = new \DateTimeZone($this->get('session')->get('campaignchain.timezone'));
        $now = new \DateTime('now', $dateTimeZone);
        $campaign->setStartDate($now);
        $campaign->setEndDate($now->modify('+1 day'));

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(self::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $repository = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $repository->getConnection()->beginTransaction();

                $repository->persist($campaign);

                // We need the campaign ID for storing the hooks. Hence we must flush here.
                $repository->flush();

                $hookService = $this->get('campaignchain.core.hook');
                $campaign = $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $form, true);

                $repository->flush();

                $repository->getConnection()->commit();
            } catch (\Exception $e) {
                $repository->getConnection()->rollback();
                throw $e;
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                'Your new campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $campaign->getId())).'">'.$campaign->getName().'</a> was created successfully.'
            );

            return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
        }

        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'Create New Scheduled Campaign',
                'form' => $form->createView(),
            ));
    }

    public function editAction(Request $request, $id)
    {
        // TODO: If a campaign is ongoing, only the end date can be changed.
        // TODO: If a campaign is done, it cannot be edited.
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(self::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $repository = $this->getDoctrine()->getManager();

            $hookService = $this->get('campaignchain.core.hook');
            $campaign = $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $form);
            $repository->persist($campaign);

            $repository->flush();

            $this->get('session')->getFlashBag()->add(
                'success',
                'Your campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $campaign->getId())).'">'.$campaign->getName().'</a> was edited successfully.'
            );

            return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
        }

        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'Edit Scheduled Campaign',
                'form' => $form->createView(),
            ));
    }

    public function editModalAction(Request $request, $id)
    {
        // TODO: If a campaign is ongoing, only the end date can be changed.
        // TODO: If a campaign is done, it cannot be edited.
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(self::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);
        $campaignType->setView('modal');

        $form = $this->createForm($campaignType, $campaign);

        return $this->render(
            'CampaignChainCoreBundle:Base:new_modal.html.twig',
            array(
                'page_title' => 'Edit Scheduled Campaign',
                'form' => $form->createView(),
            ));
    }

    public function editApiAction(Request $request, $id)
    {
        $responseData = array();

        $data = $request->get('campaignchain_core_campaign');

        $responseData['data'] = $data;

        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);
        $campaign->setName($data['name']);
        $campaign->setTimezone($data['timezone']);

        $repository = $this->getDoctrine()->getManager();
        $repository->persist($campaign);

        $hookService = $this->get('campaignchain.core.hook');
        $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $data);

        $repository->flush();

        $responseData['start_date'] = $campaign->getStartDate()->format(\DateTime::ISO8601);
        $responseData['end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);

        $encoders = array(new JsonEncoder());
        $normalizers = array(new GetSetMethodNormalizer());
        $serializer = new Serializer($normalizers, $encoders);

        $response = new Response($serializer->serialize($responseData, 'json'));
        return $response->setStatusCode(Response::HTTP_OK);
    }

    public function moveApiAction(Request $request)
    {
        $encoders = array(new JsonEncoder());
        $normalizers = array(new GetSetMethodNormalizer());
        $serializer = new Serializer($normalizers, $encoders);

        $responseData = array();

        $id = $request->request->get('id');
//        echo $request->request->get('start_date');
        $newStartDate = new \DateTime($request->request->get('start_date'));
        $newStartDate = DateTimeUtil::roundMinutes($newStartDate);

        $repository = $this->getDoctrine()->getManager();

        // Make sure that data stays intact by using transactions.
        try {
            $repository->getConnection()->beginTransaction();

            $campaign = $this->getCampaign($id);
            $responseData['campaign']['id'] = $campaign->getId();

            $oldCampaignStartDate = clone $campaign->getStartDate();
            $responseData['campaign']['old_start_date'] = $oldCampaignStartDate->format(\DateTime::ISO8601);
            $responseData['campaign']['old_end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);

            // Calculate time difference.
            $interval = $campaign->getStartDate()->diff($newStartDate);
    //        $responseData['campaign']['interval']['object'] = json_encode($interval, true);
    //        $responseData['campaign']['interval']['string'] = $interval->format(self::FORMAT_DATEINTERVAL);

            // Set new start and end date for campaign.
            $campaign->setStartDate(new \DateTime($campaign->getStartDate()->add($interval)->format(\DateTime::ISO8601)));
            $campaign->setEndDate(new \DateTime($campaign->getEndDate()->add($interval)->format(\DateTime::ISO8601)));

            $responseData['campaign']['new_start_date'] = $campaign->getStartDate()->format(\DateTime::ISO8601);
            $responseData['campaign']['new_end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);

            // Change due date of all related milestones.
            $milestones = $campaign->getMilestones();
            if($milestones->count()){
                foreach($milestones as $milestone){
                    $oldMilestoneDate = clone $milestone->getDue();
    //                $oldCampaignMilestoneInterval = $oldCampaignStartDate->diff($oldMilestoneDate);
    //                echo 'OLD: Campaign <-> '.$milestone->getName().': '.$oldCampaignMilestoneInterval->format('Years: %Y, months: %m, days: %d, hours: %h, minutes: %i, seconds: %s').' ';
                    $milestone->setDue(new \DateTime($milestone->getDue()->add($interval)->format(\DateTime::ISO8601)));
                    $milestoneInterval = $oldMilestoneDate->diff($milestone->getDue());
    //                $newCampaignMilestoneInterval = $campaign->getStartDate()->diff($milestone->getDue());
    //                echo 'NEW: Campaign <-> '.$milestone->getName().': '.$newCampaignMilestoneInterval->format('Years: %Y, months: %m, days: %d, hours: %h, minutes: %i, seconds: %s').' ';
    //                $responseData['milestones'][] = array(
    //                    'id' => $milestone->getId(),
    //                    'name' => $milestone->getName(),
    //                    'old_due_date' => $oldMilestoneDate->format(\DateTime::ISO8601),
    //                    'new_due_date' => $milestone->getDue()->format(\DateTime::ISO8601),
    //                    'interval' => array(
    //                        'object' => json_encode($milestoneInterval, true),
    //                        'string' => $milestoneInterval->format(self::FORMAT_DATEINTERVAL),
    //                    ),
    //                );
                    $campaign->addMilestone($milestone);
                }
            }

            // Change due date of all related activities.
            $activities = $campaign->getActivities();
            if($activities->count()){
                foreach($activities as $campaign){
                    // Update the trigger hook.
                    $hookService = $this->get($campaign->getTriggerHook()->getServices()['entity']);
                    $hook = $hookService->getHook($campaign);

    //                $oldHookStartDate = clone $hook->getStartDate();
    //                $oldHookEndDate = clone $hook->getEndDate();
                    $hook->setStartDate(new \DateTime($hook->getStartDate()->add($interval)->format(\DateTime::ISO8601)));
                    // TODO: Do we have to define per trigger hook whether the end date should explicitly be set or can this be handled by the hook's entity class?
                    //$hook->setEndDate(new \DateTime($hook->getEndDate()->add($interval)->format(\DateTime::ISO8601)));

                    $repository->persist($hook);
                    $repository->flush();

//                    $activityInterval = $oldActivityDate->diff($activity->getDue());
    //                $oldActivityDate = clone $activity->getDue();
    //                $activity->setDue(new \DateTime($activity->getDue()->add($interval)->format(\DateTime::ISO8601)));
    //                $activityInterval = $oldActivityDate->diff($activity->getDue());
    //                $responseData['activities'][] = array(
    //                    'id' => $activity->getId(),
    //                    'name' => $activity->getName(),
    //                    'old_due_date' => $oldMilestoneDate->format(\DateTime::ISO8601),
    //                    'new_due_date' => $activity->getDue()->format(\DateTime::ISO8601),
    //                    'interval' => array(
    //                        'object' => json_encode($activityInterval, true),
    //                        'string' => $activityInterval->format(self::FORMAT_DATEINTERVAL),
    //                    ),
    //                );
                    $campaign->addActivity($activity);
                }
            }

            $repository->persist($campaign);
            $repository->flush();

            $repository->getConnection()->commit();
        } catch (\Exception $e) {
            $repository->getConnection()->rollback();
            // TODO: Don't throw an exception, instead respond with JSON and HTTP error code.
            throw $e;
        }

        $response = new Response($serializer->serialize($responseData, 'json'));
        return $response->setStatusCode(Response::HTTP_OK);
    }

    public function getCampaign($id){
        $campaign = $this->getDoctrine()
            ->getRepository('CampaignChainCoreBundle:Campaign')
            ->find($id);

        if (!$campaign) {
            // TODO: Make sure we return this error as a response if it is an API call.
            throw new \Exception(
                'No product found for id '.$id
            );
        }

        return $campaign;
    }
}