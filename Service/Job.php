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

namespace CampaignChain\Campaign\ScheduledCampaignBundle\Service;

use CampaignChain\CoreBundle\Entity\Action;
use CampaignChain\CoreBundle\Entity\Campaign;
use Doctrine\Common\Persistence\ManagerRegistry;
use CampaignChain\CoreBundle\Job\JobActionInterface;

class Job implements JobActionInterface
{
    /**
     * @var Registry
     */
    protected $em;

    /**
     * @var string
     */
    protected $message;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->em = $managerRegistry->getManager();
    }

    public function execute($id)
    {
        /** @var Campaign $campaign */
        $campaign = $this->em
            ->getRepository('CampaignChainCoreBundle:Campaign')
            ->find($id);

        if (!$campaign) {
            throw new \Exception('No Campaign found with id: '.$id);
        }

        $now = new \DateTime('now');

        // The campaign ended.
        if($campaign->getStartDate() < $now && $campaign->getEndDate() < $now){
            $campaign->setStatus(Action::STATUS_CLOSED);
            $this->em->flush();

            $this->message =
                'The campaign "'.$campaign->getName().'" '
                .'with ID "'.$campaign->getId().'" ended, '
                .'thus its status was set to "'.Action::STATUS_CLOSED.'"';
        }

        return self::STATUS_OK;
    }

    public function getMessage()
    {
        return $this->message;
    }
}