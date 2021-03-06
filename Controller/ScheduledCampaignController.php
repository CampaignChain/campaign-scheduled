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

namespace CampaignChain\Campaign\ScheduledCampaignBundle\Controller;

use CampaignChain\CoreBundle\EntityService\HookService;
use CampaignChain\CoreBundle\Exception\ErrorCode;
use CampaignChain\CoreBundle\Form\Type\CampaignType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use CampaignChain\CoreBundle\Entity\Campaign;
use CampaignChain\CoreBundle\Entity\Action;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ScheduledCampaignController extends Controller
{
    const FORMAT_DATEINTERVAL = 'Years: %Y, months: %m, days: %d, hours: %h, minutes: %i, seconds: %s';
    const BUNDLE_NAME = 'campaignchain/campaign-scheduled';
    const MODULE_IDENTIFIER = 'campaignchain-scheduled';

    public function getLogger()
    {
        return $this->has('monolog.logger.external') ? $this->get('monolog.logger.external') : $this->get('monolog.logger');
    }

    public function newAction(Request $request)
    {
        // create a campaign and give it some dummy data for this example
        $campaign = new Campaign();
        $campaign->setTimezone($this->get('session')->get('campaignchain.timezone'));
        $dateTimeZone = new \DateTimeZone($this->get('session')->get('campaignchain.timezone'));
        $now = new \DateTime('now', $dateTimeZone);
        $campaign->setStartDate($now);
        $campaign->setEndDate($now->modify('+1 day'));

        $form = $this->createForm(CampaignType::class, $campaign, $this->getCampaignTypeOptions());

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $em->getConnection()->beginTransaction();

                $em->persist($campaign);

                // We need the campaign ID for storing the hooks. Hence we must flush here.
                $em->flush();

                /** @var HookService $hookService */
                $hookService = $this->get('campaignchain.core.hook');
                $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $form, true);
                $campaign = $hookService->getEntity();

                $em->flush();

                $em->getConnection()->commit();
            } catch (\Exception $e) {
                $em->getConnection()->rollback();
                throw $e;
            }

            $this->addFlash(
                'success',
                'Your new campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $campaign->getId())).'">'.$campaign->getName().'</a> was created successfully.'
            );

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(array(
                    'step' => 2
                ));
            } else {
                return $this->redirectToRoute('campaignchain_core_plan_campaigns');
            }

        }

        return $this->render(
            $request->isXmlHttpRequest() ? 'CampaignChainCoreBundle:Base:new_modal.html.twig' : 'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'Create New Scheduled Campaign',
                'form' => $form->createView(),
            ));
    }

    public function editAction(Request $request, $id)
    {
        $campaignService = $this->get('campaignchain.core.campaign');
        /** @var Campaign $campaign */
        $campaign = $campaignService->getCampaign($id);

        $form = $this->createForm(CampaignType::class, $campaign, $this->getCampaignTypeOptions());

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            try {
                $em->getConnection()->beginTransaction();

                /** @var HookService $hookService */
                $hookService = $this->get('campaignchain.core.hook');
                $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $form);
                $campaign = $hookService->getEntity();
                $em->persist($campaign);
                $em->flush();

                $this->addFlash(
                    'success',
                    'Your campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $campaign->getId())).'">'.$campaign->getName().'</a> was edited successfully.'
                );

                $em->getConnection()->commit();
            } catch (\Exception $e) {
                $em->getConnection()->rollback();
                $this->addFlash(
                    'warning',
                    $e->getMessage()
                );
            }
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
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $form = $this->createForm(CampaignType::class, $campaign, $this->getCampaignTypeOptions());

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
        /** @var Campaign $campaign */
        $campaign = $campaignService->getCampaign($id);
        $campaign->setName($data['name']);
        $campaign->setTimezone($data['timezone']);

        // Clear all flash bags.
        $this->get('session')->getFlashBag()->clear();

        $em = $this->getDoctrine()->getManager();

        // Make sure that data stays intact by using transactions.
        try {
            $em->getConnection()->beginTransaction();

            $em->persist($campaign);

            /** @var HookService $hookService */
            $hookService = $this->get('campaignchain.core.hook');
            $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $data);
            $campaign = $hookService->getEntity();

            if($hookService->hasErrors()){
                foreach($hookService->getErrorCodes() as $errorCode){
                    $responseData['message'] = ErrorCode::getMessageByCode($errorCode);
                }

                $responseData['success'] = false;

                if($campaign->getPostStartDateLimit()){
                    $responseData['post_start_date_limit'] = $campaign->getPostStartDateLimit()->format(\DateTime::ISO8601);
                }
            } else {
                $em->flush();

                $responseData['success'] = true;
            }

            $responseData['start_date'] = $campaign->getStartDate()->format(\DateTime::ISO8601);
            $responseData['end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();

//            $this->addFlash(
//                'warning',
//                $e->getMessage()
//            );

            $this->getLogger()->error($e->getMessage(), array(
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ));

            $responseData['message'] = $e->getMessage();
            $responseData['success'] = false;
        }

        $responseData['status'] = $campaign->getStatus();

        $serializer = $this->get('campaignchain.core.serializer.default');

        return new Response($serializer->serialize($responseData, 'json'));
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

                $campaignTypeOptions = $this->getCampaignTypeOptions();
                $campaignTypeOptions['view'] = 'copy';
                $campaignTypeOptions['hooks_options'] =
                    array(
                        'campaignchain-due' => array(
                            'label' => 'Start Date',
                            'help_text' => 'Ends after '.$interval->format("%a").' days.',
                        )
                    );

                $form = $this->createForm(CampaignType::class, $toCampaign, $campaignTypeOptions);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->get('campaignchain.campaign.scheduled.copy');
                    $hookData = $form->get('campaignchain_hook_campaignchain_due')->getData();
                    $clonedCampaign = $copyService->scheduled2Scheduled(
                        $fromCampaign, $hookData->getStartDate(),
                        Action::STATUS_OPEN, $toCampaign->getName()
                    );

                    $this->addFlash(
                        'success',
                        'Your scheduled campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                    );

                    return $this->redirectToRoute('campaignchain_core_campaign');
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

                $campaignTypeOptions = $this->getCampaignTypeOptions();
                $campaignTypeOptions['view'] = 'copy';
                $campaignTypeOptions['hooks_options'] =
                    array(
                        'campaignchain-due' => array(
                            'label' => 'Start Date',
                            'help_text' => 'Ends after '.$interval->format("%a").' days.',
                        )
                    );

                $form = $this->createForm(CampaignType::class, $scheduledCampaignForm, $campaignTypeOptions);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->get('campaignchain.campaign.scheduled.copy');
                    $hookData = $form->get('campaignchain_hook_campaignchain_due')->getData();
                    $clonedCampaign = $copyService->template2Scheduled(
                        $campaignTemplate, $hookData->getStartDate(),
                        Action::STATUS_OPEN, $scheduledCampaignForm->getName()
                    );

                    $this->addFlash(
                        'success',
                        'The scheduled campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                    );

                    return $this->redirectToRoute('campaignchain_core_campaign');
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

                $campaignTypeOptions = $this->getCampaignTypeOptions();
                $campaignTypeOptions['view'] = 'copy';
                $campaignTypeOptions['hooks_options'] =
                    array(
                        'campaignchain-due' => array(
                            'label' => 'Start Date',
                            'help_text' => 'Ends after '.$interval->format("%a").' days.',
                        )
                    );

                $form = $this->createForm(CampaignType::class, $scheduledCampaignForm, $campaignTypeOptions);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->container->get('campaignchain.campaign.repeating.copy');
                    $hookData = $form->get('campaignchain_hook_campaignchain_due')->getData();
                    $clonedCampaign = $copyService->repeating2Scheduled(
                        $repeatingCampaign, $hookData->getStartDate(),
                        Action::STATUS_OPEN, $scheduledCampaignForm->getName()
                    );

                    $this->addFlash(
                        'success',
                        'The scheduled campaign <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                    );

                    return $this->redirectToRoute('campaignchain_core_campaign');
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

    protected function getCampaignTypeOptions() {
        $options['bundle_name'] = static::BUNDLE_NAME;
        $options['module_identifier'] = static::MODULE_IDENTIFIER;

        return $options;
    }
}