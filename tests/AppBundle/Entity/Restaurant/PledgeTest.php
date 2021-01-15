<?php

namespace Tests\AppBundle\Entity\Restaurant;

use PHPUnit\Framework\TestCase;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Entity\Address;
use AppBundle\Entity\User;

class PledgeTest extends TestCase
{
    public function testAddVote()
    {
    	$pledge = new Pledge();
    	$user = new User();

    	$pledge->addVote($user);

    	$this->assertEquals(1, count($pledge->getVotes()));
    }

    public function testHasVoted()
    {
    	$pledge = new Pledge();
    	$user1 = new User();
    	$user2 = new User();

    	$pledge->addVote($user1);

    	$this->assertTrue($pledge->hasVoted($user1));
    	$this->assertFalse($pledge->hasVoted($user2));

		$pledge->addVote($user2);

		$this->assertTrue($pledge->hasVoted($user2));
    }

    public function testSameUserCannotVoteTwice()
    {
    	$pledge = new Pledge();
    	$user1 = new User();

    	$pledge->addVote($user1);
    	$pledge->addVote($user1);

    	$this->assertEquals(1, count($pledge->getVotes()));
    }

    public function testAccept() {
        $pledge = new Pledge();
        $address = new Address();
        $pledge->setName('test');
        $pledge->setAddress($address);
        $pledge->setState('new');

        $restaurant = $pledge->accept();
        $this->assertEquals('test', $restaurant->getName());
        $this->assertSame($address, $restaurant->getAddress());
        $this->assertSame($pledge, $restaurant->getPledge());
        $this->assertEquals('pledge', $restaurant->getState());
        $this->assertEquals('accepted', $pledge->getState());
        $this->assertTrue($restaurant->isEnabled());
    }

    public function testAcceptThrowsException() {
        $this->expectException(\Exception::class);
        $pledge = new Pledge();
        $address = new Address();
        $pledge->setState('notNew');
        $pledge->setName('test');
        $pledge->setAddress($address);
        $pledge->accept();
    }
}
