<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
            $repository = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $repository->getConnection()->beginTransaction();

                $repository->persist($campaign);

                // We need the campaign ID for storing the hooks. Hence we must flush here.
                $repository->flush();

                $hookService = $this->get('campaignchain.core.hook');
                $campaign = $hookService->processHooks(ScheduledCampaignController::BUNDLE_NAME, ScheduledCampaignController::MODULE_IDENTIFIER, $campaign, $form, true);

                $repository->flush();

                $repository->getConnection()->commit();
            } catch (\Exception $e) {
                $repository->getConnection()->rollback();

                return $this->errorResponse($e->getMessage());
            }

            return $this->response(['campaign' => ['id' => $campaign->getId()]]);
        }

        return $this->errorResponse($form);
    }
}