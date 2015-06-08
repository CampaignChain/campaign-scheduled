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
use CampaignChain\CoreBundle\Entity\Module;
use CampaignChain\CoreBundle\Entity\Action;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

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
            'CampaignChainCoreBundle:Campaign:edit.html.twig',
            array(
                'page_title' => 'Edit Scheduled Campaign',
                'form' => $form->createView(),
                'campaign' => $campaign,
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
        $campaignType->setView('default');

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

    public function convertAction(Request $request, $id)
    {
        $campaignService = $this->get('campaignchain.core.campaign');
        $fromCampaign = $campaignService->getCampaign($id);

        $bundleName = $fromCampaign->getCampaignModule()->getBundle()->getName();
        $moduleIdentifier = $fromCampaign->getCampaignModule()->getIdentifier();
        $campaignURI = $bundleName.'/'.$moduleIdentifier;

        switch($campaignURI){
            case 'campaignchain/campaign-template/campaignchain-template':

                $campaignTemplate = $fromCampaign;
                $convertedCampaign = clone $campaignTemplate;

                $convertedCampaign->setName($campaignTemplate->getName().' (converted)');
                $interval = $campaignTemplate->getStartDate()->diff($campaignTemplate->getEndDate());
                $convertedCampaign->setStartDate(new \DateTime('now'));

                $campaignType = $this->get('campaignchain.core.form.type.campaign');
                $campaignType->setBundleName(self::BUNDLE_NAME);
                $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);
                $campaignType->setView('convert');
                $campaignType->setHooksOptions(
                    array(
                        'campaignchain-due' => array(
                            'label' => 'Start Date',
                            'help_text' => 'Ends after '.$interval->format("%a").' days.',
                        )
                    )
                );

                $form = $this->createForm($campaignType, $convertedCampaign);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    // Clone the campaign template.
                    $clonedCampaign = $campaignService->cloneCampaign(
                        $campaignTemplate
                    );

                    // Change module relationship of cloned campaign
                    $moduleService = $this->get('campaignchain.core.module');
                    $clonedCampaign->setCampaignModule(
                        $moduleService->getModule(
                            Module::REPOSITORY_CAMPAIGN,
                            self::BUNDLE_NAME,
                            self::MODULE_IDENTIFIER
                        )
                    );
                    // Specify other parameters of converted campaign.
                    $clonedCampaign->setName($convertedCampaign->getName());
                    $clonedCampaign->setHasRelativeDates(false);
                    $clonedCampaign->setStatus(Action::STATUS_OPEN);
                    $hookService = $this->get('campaignchain.core.hook');
                    $clonedCampaign->setTriggerHook(
                        $hookService->getHook('campaignchain-duration')
                    );

                    $repository = $this->getDoctrine()->getManager();
                    $repository->flush();

                    // Move the cloned campaign to the start date.
                    $hookData = $form->get('campaignchain_hook_campaignchain_due')->getData();
                    $clonedCampaign = $campaignService->moveCampaign(
                        $clonedCampaign, $hookData->getStartDate(),
                        Action::STATUS_OPEN
                    );

                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'Your campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was created successfully.'
                    );

                    return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Convert Template to Scheduled Campaign',
                        'form' => $form->createView(),
                    ));

                break;
        }
    }
}