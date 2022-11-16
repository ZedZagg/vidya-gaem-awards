<?php
namespace App\Entity;

use DateTime;
use Symfony\Component\Security\Core\User\UserInterface;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="voting_code_logs")
 * @ORM\Entity
 */
class VotingCodeLog
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="cookie_id", type="string", length=255, nullable=false)
     */
    private $cookieID;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="timestamp", type="datetime", nullable=false)
     */
    private $timestamp;

    /**
     * @var string
     *
     * @ORM\Column(name="ip", type="string", length=45, nullable=false)
     */
    private $ip;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=20, nullable=false)
     */
    private $code;

    /**
     * @var string|null
     *
     * @ORM\Column(name="referer", type="string", length=255, nullable=true)
     */
    private $referer;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="votes")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="userID", referencedColumnName="id")
     * })
     */
    private $user;

    public function construct()
    {
        $this->timestamp = new DateTime();
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User|UserInterface $user
     * @return VotingCodeLog
     */
    public function setUser($user): VotingCodeLog
    {
        if ($user->isLoggedIn()) {
            $this->user = $user;
        }
        $this->ip = $user->getIP();
        $this->cookieID = $user->getRandomID();
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    /**
     * @param DateTime $timestamp
     * @return VotingCodeLog
     */
    public function setTimestamp($timestamp): VotingCodeLog
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return VotingCodeLog
     */
    public function setCode($code): VotingCodeLog
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getReferer(): string
    {
        return $this->referer;
    }

    /**
     * @param string $referer
     * @return VotingCodeLog
     */
    public function setReferer($referer): VotingCodeLog
    {
        $this->referer = mb_substr($referer, 0, 190);
        return $this;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     * @return VotingCodeLog
     */
    public function setIp($ip): VotingCodeLog
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return string
     */
    public function getCookieID(): string
    {
        return $this->cookieID;
    }

    /**
     * @param string $cookieID
     * @return VotingCodeLog
     */
    public function setCookieID($cookieID): VotingCodeLog
    {
        $this->cookieID = $cookieID;
        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
