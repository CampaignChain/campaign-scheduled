<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
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
use Symfony\Component\HttpFoundation\JsonResponse;
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

            if ($this->getRequest()->isXmlHttpRequest()) {
                return new JsonResponse(array(
                    'step' => 2
                ));
            } else {
                return $this->redirectToRoute('campaignchain_core_campaign');
            }

        }

        return $this->render(
            $this->getRequest()->isXmlHttpRequest() ? 'CampaignChainCoreBundle:Base:new_modal.html.twig' : 'CampaignChainCoreBundle:Base:new.html.twig',
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
            'CampaignChainCampaignScheduledCampaignBundle::edit.html.twig',
            array(
                'page_title' => 'Edit Scheduled Campaign',
                'page_secondary_title' => $campaign->getName(),
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
            'CampaignChainCoreBundle:Campaign:edit_modal.html.twig',
            array(
                'page_title' => 'Edit Scheduled Campaign',
                'form' => $form->createView(),
                'campaign' => $campaign,
                'form_submit_label' => 'Save',
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

    public function copyAction(Request $request, $id)
    {
        $campaignService = $this->get('campaignchain.core.campaign');
        $fromCampaign = $campaignService->getCampaign($id);
        $campaignURI = $campaignService->getCampaignURI($fromCampaign);

        switch($campaignURI){
            case 'campaignchain/campaign-scheduled/campaignchain-scheduled':
                $toCampaign = clone $fromCampaign;
                $toCampaign->setName($fromCampaign->getName().' (copied)');
                $interval = $toCampaign->getStartDate()->diff($toCampaign->getEndDate());
                $toCampaign->setStartDate(new \DateTime('now'));

                $campaignType = $this->get('campaignchain.core.form.type.campaign');
                $campaignType->setBundleName(self::BUNDLE_NAME);
                $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);
                $campaignType->setView('copy');
                $campaignType->setHooksOptions(
                    array(
                        'campaignchain-due' => array(
                            'label' => 'Start Date',
                            'help_text' => 'Ends after '.$interval->format("%a").' days.',
                        )
                    )
                );

                $form = $this->createForm($campaignType, $toCampaign);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->get('campaignchain.campaign.scheduled.copy');
                    $hookData = $form->get('campaignchain_hook_campaignchain_due')->getData();
                    $clonedCampaign = $copyService->scheduled2Scheduled(
                        $fromCampaign, $hookData->getStartDate(),
                        Action::STATUS_OPEN, $toCampaign->getName()
                    );

                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'Your scheduled campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                    );


                    return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Copy Scheduled Campaign',
                        'form' => $form->createView(),
                    ));
                break;

            case 'campaignchain/campaign-template/campaignchain-template':

                $campaignTemplate = $fromCampaign;
                $scheduledCampaignForm = clone $campaignTemplate;

                $scheduledCampaignForm->setName($campaignTemplate->getName().' (copied)');
                $interval = $campaignTemplate->getStartDate()->diff($campaignTemplate->getEndDate());
                $scheduledCampaignForm->setStartDate(new \DateTime('now'));

                $campaignType = $this->get('campaignchain.core.form.type.campaign');
                $campaignType->setBundleName(self::BUNDLE_NAME);
                $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);
                $campaignType->setView('copy');
                $campaignType->setHooksOptions(
                    array(
                        'campaignchain-due' => array(
                            'label' => 'Start Date',
                            'help_text' => 'Ends after '.$interval->format("%a").' days.',
                        )
                    )
                );

                $form = $this->createForm($campaignType, $scheduledCampaignForm);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->get('campaignchain.campaign.scheduled.copy');
                    $hookData = $form->get('campaignchain_hook_campaignchain_due')->getData();
                    $clonedCampaign = $copyService->template2Scheduled(
                        $campaignTemplate, $hookData->getStartDate(),
                        Action::STATUS_OPEN, $scheduledCampaignForm->getName()
                    );

                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'The scheduled campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                    );

                    return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Copy Template as Scheduled Campaign',
                        'form' => $form->createView(),
                    ));

                break;
            case 'campaignchain/campaign-repeating/campaignchain-repeating':
                $repeatingCampaign = $fromCampaign;
                $scheduledCampaignForm = clone $repeatingCampaign;

                $scheduledCampaignForm->setName($repeatingCampaign->getName().' (copied)');
                $interval = $repeatingCampaign->getStartDate()->diff($repeatingCampaign->getEndDate());
                $scheduledCampaignForm->setStartDate(new \DateTime('now'));

                $campaignType = $this->get('campaignchain.core.form.type.campaign');
                $campaignType->setBundleName(self::BUNDLE_NAME);
                $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);
                $campaignType->setView('copy');
                $campaignType->setHooksOptions(
                    array(
                        'campaignchain-due' => array(
                            'label' => 'Start Date',
                            'help_text' => 'Ends after '.$interval->format("%a").' days.',
                        )
                    )
                );

                $form = $this->createForm($campaignType, $scheduledCampaignForm);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->container->get('campaignchain.campaign.repeating.copy');
                    $hookData = $form->get('campaignchain_hook_campaignchain_due')->getData();
                    $clonedCampaign = $copyService->repeating2Scheduled(
                        $repeatingCampaign, $hookData->getStartDate(),
                        Action::STATUS_OPEN, $scheduledCampaignForm->getName()
                    );

                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'The scheduled campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                    );

                    return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Copy Repeating Campaign as Scheduled Campaign',
                        'form' => $form->createView(),
                    ));
                break;
        }
    }
}