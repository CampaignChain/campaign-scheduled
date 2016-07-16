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

namespace CampaignChain\Campaign\ScheduledCampaignBundle\Controller\REST;

use CampaignChain\Campaign\ScheduledCampaignBundle\Controller\ScheduledCampaignController;
use CampaignChain\CoreBundle\Controller\REST\BaseController;
use CampaignChain\CoreBundle\Entity\Campaign;
use FOS\RestBundle\Controller\Annotations as REST;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * @REST\NamePrefix("campaignchain_campaign_scheduled_rest_")
 *
 * Class ScheduledCampaignRESTController
 * @package CampaignChain\Campaign\ScheduledCampaignBundle\Controller\REST
 */
class ScheduledCampaignRESTController extends BaseController
{

    /**
     * Create new Scheduled Campaign
     *
     * Example Request
     * ===============
     *
     *      POST /api/v1/p/campaignchain/campaign-scheduled/campaigns
     *
     * Example Input
     * =============
     *
    {
        "campaign": {
            "name": "REST4 Campaign",
            "timezone": "UTC",
            "campaignchain_hook_campaignchain_duration": {
                "startDate": "2016-05-05T12:00:00+0000",
                "endDate": "2016-06-05T12:00:00+0000",
                "timezone": "UTC"
            }
        }
    }
     *
     *
     * Example Response
     * ================
     *
     {
       "campaign": {
         "id": 5
       }
     }
     *
     * @ApiDoc(section="Campaigns")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postCampaignAction(Request $request)
    {
        $campaign = new Campaign();

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setView('rest');
        $campaignType->setBundleName(ScheduledCampaignController::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(ScheduledCampaignController::MODULE_IDENTIFIER);

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $em->getConnection()->beginTransaction();

                $em->persist($campaign);

                // We need the campaign ID for storing the hooks. Hence we must flush here.
                $em->flush();

                $hookService = $this->get('campaignchain.core.hook');
                $campaign = $hookService->processHooks(ScheduledCampaignController::BUNDLE_NAME, ScheduledCampaignController::MODULE_IDENTIFIER, $campaign, $form, true);

                $em->flush();

                $em->getConnection()->commit();
            } catch (\Exception $e) {
                $em->getConnection()->rollback();

                return $this->errorResponse($e->getMessage());
            }

            return $this->response(['campaign' => ['id' => $campaign->getId()]]);
        }

        return $this->errorResponse($form);
    }
}