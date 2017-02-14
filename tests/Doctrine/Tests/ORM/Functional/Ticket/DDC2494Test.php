<?php

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Annotation as ORM;

/**
 * @group DDC-2494
 * @group non-cacheable
 */
class DDC2494Test extends \Doctrine\Tests\OrmFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        DDC2494TinyIntType::$calls = [];

        Type::addType('ddc2494_tinyint', DDC2494TinyIntType::class);

        $this->schemaTool->createSchema(
            [
            $this->em->getClassMetadata(DDC2494Currency::class),
            $this->em->getClassMetadata(DDC2494Campaign::class),
            ]
        );
    }

    public function testIssue()
    {
        $currency = new DDC2494Currency(1, 2);

        $this->em->persist($currency);
        $this->em->flush();

        $campaign = new DDC2494Campaign($currency);

        $this->em->persist($campaign);
        $this->em->flush();
        $this->em->close();

        self::assertArrayHasKey('convertToDatabaseValue', DDC2494TinyIntType::$calls);
        self::assertCount(3, DDC2494TinyIntType::$calls['convertToDatabaseValue']);

        $item = $this->em->find(DDC2494Campaign::class, $campaign->getId());

        self::assertInstanceOf(DDC2494Campaign::class, $item);
        self::assertInstanceOf(DDC2494Currency::class, $item->getCurrency());

        $queryCount = $this->getCurrentQueryCount();

        self::assertInstanceOf('\Doctrine\Common\Proxy\Proxy', $item->getCurrency());
        self::assertFalse($item->getCurrency()->__isInitialized());

        self::assertArrayHasKey('convertToPHPValue', DDC2494TinyIntType::$calls);
        self::assertCount(1, DDC2494TinyIntType::$calls['convertToPHPValue']);

        self::assertInternalType('integer', $item->getCurrency()->getId());
        self::assertCount(1, DDC2494TinyIntType::$calls['convertToPHPValue']);
        self::assertFalse($item->getCurrency()->__isInitialized());

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInternalType('integer', $item->getCurrency()->getTemp());
        self::assertCount(3, DDC2494TinyIntType::$calls['convertToPHPValue']);
        self::assertTrue($item->getCurrency()->__isInitialized());

        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }
}

/**
 * @ORM\Table(name="ddc2494_currency")
 * @ORM\Entity
 */
class DDC2494Currency
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", type="ddc2494_tinyint")
     */
    protected $id;

    /**
     * @ORM\Column(name="temp", type="ddc2494_tinyint", nullable=false)
     */
    protected $temp;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DDC2494Campaign", mappedBy="currency")
     */
    protected $campaigns;

    public function __construct($id, $temp)
    {
        $this->id   = $id;
        $this->temp = $temp;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTemp()
    {
        return $this->temp;
    }

    public function getCampaigns()
    {
        return $this->campaigns;
    }
}

/**
 * @ORM\Table(name="ddc2494_campaign")
 * @ORM\Entity
 */
class DDC2494Campaign
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var \Doctrine\Tests\ORM\Functional\Ticket\DDC2494Currency
     *
     * @ORM\ManyToOne(targetEntity="DDC2494Currency", inversedBy="campaigns")
     * @ORM\JoinColumn(name="currency_id", referencedColumnName="id", nullable=false)
     */
    protected $currency;

    public function __construct(DDC2494Currency $currency)
    {
        $this->currency = $currency;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \Doctrine\Tests\ORM\Functional\Ticket\DDC2494Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }
}

class DDC2494TinyIntType extends Type
{
    public static $calls = [];

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getSmallIntTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        $return = (string) $value;

        self::$calls[__FUNCTION__][] = [
            'value'     => $value,
            'return'    => $return,
            'platform'  => $platform,
        ];

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $return = (integer) $value;

        self::$calls[__FUNCTION__][] = [
            'value'     => $value,
            'return'    => $return,
            'platform'  => $platform,
        ];

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ddc2494_tinyint';
    }
}
