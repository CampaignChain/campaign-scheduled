<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Campaign\ScheduledCampaignBundle\Service;

use CampaignChain\CoreBundle\Entity\Action;
use CampaignChain\CoreBundle\Entity\Campaign;
use Doctrine\ORM\EntityManager;
use CampaignChain\CoreBundle\Job\JobActionInterface;

class Job implements JobActionInterface
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var string
     */
    protected $message;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
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