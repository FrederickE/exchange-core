<?php

namespace Hydra\Exchange\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hydra\Exchange\Libs\Deal;
use Hydra\Exchange\Entities\Abstracts\Order;
use Hydra\Exchange\Entities\{Pair, BuyOrder, SellOrder, SellerBalance, BuyerBalance, Asset};
use Hydra\Exchange\Exceptions\Balance as BalanceException;
use Hydra\Exchange\Exceptions\Deal as DealException;
use Hydra\Exchange\Libs\{Matcher, Logger};

class MatchingTest extends TestCase
{
    /**
     * @return void
     */
    public function testFloatPrice()
    {
        $pair = new Pair(
            new Asset("BTC", "Bitcoin"), //primary asset
            new Asset("ETH", "Ether") //secondary asset
        );

        $buyersBalance = new BuyerBalance(1001, 89);
        $sellerBalance = new SellerBalance(99, 11);

        $buyOrder = new BuyOrder($pair, 100, 10.001, $buyersBalance, 1);
        $sellOrder = new SellOrder($pair, 10, 9.1009, $sellerBalance, 2);

        //check balance freezing before execution
        $this->assertEquals(0.9, $buyersBalance->getPrimary());

        //exchange should take the two top orders from orderbook and sent them to matching
        $matcher = new Matcher($buyOrder, $sellOrder);
        $deal = $matcher->matching();

        //check the deal result
        $this->assertEquals(Deal::TYPE_SELLER_TAKER, $deal->getType()); //the seller is taker
        $this->assertEquals(10.001, $deal->getPrice());
        $this->assertEquals(10, $deal->getQuantity());

        //so, now we can check the client balances
        $this->assertEquals(900.99, $buyersBalance->getPrimary());
        $this->assertEquals(99, $buyersBalance->getSecondary());

        $this->assertEquals(199.01, $sellerBalance->getPrimary());
        $this->assertEquals(1, $sellerBalance->getSecondary());

        //check the remainit volume of assets in orders
        $this->assertEquals(90, $buyOrder->getQuantityRemain());
        $this->assertEquals(0, $sellOrder->getQuantityRemain());

        $this->assertEquals((string) Order::STATUS_PARTIAL . (string) Order::STATUS_EMPTY, (string) $buyOrder->getStatus() . $sellOrder->getStatus());
    }

    /**
     * @return void
     */
    public function testFreezeingOfAssets()
    {
        $pair = new Pair(
            new Asset("BTC", "Bitcoin"), //primary asset
            new Asset("ETH", "Ether") //secondary asset
        );

        $buyersBalance = new BuyerBalance(1001, 89);
        $sellerBalance = new SellerBalance(99, 11);

        $buyOrder = new BuyOrder($pair, 100, 10, $buyersBalance, 1);
        $sellOrder = new SellOrder($pair, 10, 9, $sellerBalance, 2);

        //check balance freezing before execution
        $this->assertEquals(1, $buyersBalance->getPrimary());
        $this->assertEquals(89, $buyersBalance->getSecondary());
        $this->assertEquals(99, $sellerBalance->getPrimary());
        $this->assertEquals(1, $sellerBalance->getSecondary());
    }

    /**
     * @return void
     */
    public function testBasicExchanging()
    {
        $pair = new Pair(
            new Asset("BTC", "Bitcoin"), //primary asset
            new Asset("ETH", "Ether") //secondary asset
        );

        $buyersBalance = new BuyerBalance(1001, 89);
        $sellerBalance = new SellerBalance(99, 11);

        //creation of deal (in matching)

        //buyer wants to buy 100 units of the secondary assets for the price of 10
        //it will cost 1000 units of the primary asset
        $buyOrder = new BuyOrder($pair, 100, 10, $buyersBalance, 1);

        //seller wants to sell 10 units of the secondary assets for the price of 9
        //it will cost 10 units of the secondary asset
        $sellOrder = new SellOrder($pair, 10, 9, $sellerBalance, 2);

        $this->assertEquals((string) Order::STATUS_ACTIVE . (string) Order::STATUS_ACTIVE, (string)$buyOrder->getStatus() . $sellOrder->getStatus());

        //exchange should take the two top orders from orderbook and sent them to matching
        $matcher = new Matcher($buyOrder, $sellOrder);
        $deal = $matcher->matching();

        //check the deal result
        $this->assertEquals(Deal::TYPE_SELLER_TAKER, $deal->getType()); //the seller is taker
        $this->assertEquals(10, $deal->getPrice());
        $this->assertEquals(10, $deal->getQuantity());

        //so, now we can check the client balances
        $this->assertEquals(901, $buyersBalance->getPrimary());
        $this->assertEquals(99, $buyersBalance->getSecondary());

        $this->assertEquals(199, $sellerBalance->getPrimary());
        $this->assertEquals(1, $sellerBalance->getSecondary());

        //check the remainit volume of assets in orders
        $this->assertEquals(90, $buyOrder->getQuantityRemain());
        $this->assertEquals(0, $sellOrder->getQuantityRemain());

        $this->assertEquals((string) Order::STATUS_PARTIAL . (string) Order::STATUS_EMPTY, (string) $buyOrder->getStatus() . $sellOrder->getStatus());
    }

    public function testSellerBalanceCrash()
    {
        $this->expectException(BalanceException::class);

        $pair = new Pair(
            new Asset("BTC", "Bitcoin"), //primary asset
            new Asset("ETH", "Ether") //secondary asset
        );

        $buyersBalance = new BuyerBalance(999, 89);
        $sellerBalance = new SellerBalance(99, 11);

        $buyOrder = new BuyOrder($pair, 100, 10, $buyersBalance, 1);
        $sellOrder = new SellOrder($pair, 10, 9, $sellerBalance, 2);

        $matcher = new Matcher($buyOrder, $sellOrder);
        $deal = $matcher->matching();
    }

    public function testBuyerBalanceCrash()
    {
        $this->expectException(BalanceException::class);

        $pair = new Pair(
            new Asset("BTC", "Bitcoin"), //primary asset
            new Asset("ETH", "Ether") //secondary asset
        );

        $buyersBalance = new BuyerBalance(1000, 89);
        $sellerBalance = new SellerBalance(99, 9);

        $buyOrder = new BuyOrder($pair, 100, 10, $buyersBalance, 1);
        $sellOrder = new SellOrder($pair, 10, 9, $sellerBalance, 2);

        $matcher = new Matcher($buyOrder, $sellOrder);
        $deal = $matcher->matching();
    }
}
