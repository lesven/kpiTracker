<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\KPIStatus;
use PHPUnit\Framework\TestCase;

/**
 * Test für das KPIStatus Value Object.
 *
 * Testet alle Factory-Methoden, Vergleichsoperationen und Business Logic
 * des KPIStatus Value Objects einschließlich der Type-Safety.
 */
class KPIStatusTest extends TestCase
{
    /**
     * @test
     */
    public function kannGruenenStatusErstellen(): void
    {
        $status = KPIStatus::green();

        $this->assertTrue($status->isGreen());
        $this->assertFalse($status->isYellow());
        $this->assertFalse($status->isRed());
        $this->assertSame('green', $status->toString());
    }

    /**
     * @test
     */
    public function kannGelbenStatusErstellen(): void
    {
        $status = KPIStatus::yellow();

        $this->assertFalse($status->isGreen());
        $this->assertTrue($status->isYellow());
        $this->assertFalse($status->isRed());
        $this->assertSame('yellow', $status->toString());
    }

    /**
     * @test
     */
    public function kannRotenStatusErstellen(): void
    {
        $status = KPIStatus::red();

        $this->assertFalse($status->isGreen());
        $this->assertFalse($status->isYellow());
        $this->assertTrue($status->isRed());
        $this->assertSame('red', $status->toString());
    }

    /**
     * @test
     */
    public function kannStatusAusStringErstellen(): void
    {
        $greenStatus = KPIStatus::fromString('green');
        $yellowStatus = KPIStatus::fromString('yellow');
        $redStatus = KPIStatus::fromString('red');

        $this->assertTrue($greenStatus->isGreen());
        $this->assertTrue($yellowStatus->isYellow());
        $this->assertTrue($redStatus->isRed());
    }

    /**
     * @test
     */
    public function wirftExceptionBeiUngueltigemString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid KPI status: invalid');

        KPIStatus::fromString('invalid');
    }

    /**
     * @test
     */
    public function kannPruefenObKritisch(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertFalse($green->isCritical());
        $this->assertTrue($yellow->isCritical());
        $this->assertTrue($red->isCritical());
    }

    /**
     * @test
     */
    public function kannPruefenObOk(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertTrue($green->isOk());
        $this->assertFalse($yellow->isOk());
        $this->assertFalse($red->isOk());
    }

    /**
     * @test
     */
    public function kannStatusVergleichenFuerVerschlechterung(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        // Grün zu Gelb = Verschlechterung
        $this->assertTrue($yellow->isWorseThan($green));
        $this->assertFalse($green->isWorseThan($yellow));

        // Grün zu Rot = Verschlechterung
        $this->assertTrue($red->isWorseThan($green));
        $this->assertFalse($green->isWorseThan($red));

        // Gelb zu Rot = Verschlechterung
        $this->assertTrue($red->isWorseThan($yellow));
        $this->assertFalse($yellow->isWorseThan($red));

        // Gleicher Status = keine Verschlechterung
        $this->assertFalse($green->isWorseThan($green));
        $this->assertFalse($yellow->isWorseThan($yellow));
        $this->assertFalse($red->isWorseThan($red));
    }

    /**
     * @test
     */
    public function kannStatusVergleichenFuerVerbesserung(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        // Gelb zu Grün = Verbesserung
        $this->assertTrue($green->isBetterThan($yellow));
        $this->assertFalse($yellow->isBetterThan($green));

        // Rot zu Grün = Verbesserung
        $this->assertTrue($green->isBetterThan($red));
        $this->assertFalse($red->isBetterThan($green));

        // Rot zu Gelb = Verbesserung
        $this->assertTrue($yellow->isBetterThan($red));
        $this->assertFalse($red->isBetterThan($yellow));

        // Gleicher Status = keine Verbesserung
        $this->assertFalse($green->isBetterThan($green));
        $this->assertFalse($yellow->isBetterThan($yellow));
        $this->assertFalse($red->isBetterThan($red));
    }

    /**
     * @test
     */
    public function kannGleichheitPruefen(): void
    {
        $green1 = KPIStatus::green();
        $green2 = KPIStatus::green();
        $yellow = KPIStatus::yellow();

        $this->assertTrue($green1->equals($green2));
        $this->assertFalse($green1->equals($yellow));
    }

    /**
     * @test
     */
    public function kannHierarchieWertAbrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertSame(1, $green->getHierarchyValue());
        $this->assertSame(2, $yellow->getHierarchyValue());
        $this->assertSame(3, $red->getHierarchyValue());
    }

    /**
     * @test
     */
    public function kannAggregiertenStatusBerechnen(): void
    {
        // Alle grün = grün
        $allGreen = [KPIStatus::green(), KPIStatus::green(), KPIStatus::green()];
        $this->assertTrue(KPIStatus::getAggregatedStatus($allGreen)->isGreen());

        // Mindestens ein rot = rot
        $withRed = [KPIStatus::green(), KPIStatus::yellow(), KPIStatus::red()];
        $this->assertTrue(KPIStatus::getAggregatedStatus($withRed)->isRed());

        // Kein rot aber mindestens ein gelb = gelb
        $withYellow = [KPIStatus::green(), KPIStatus::yellow(), KPIStatus::green()];
        $this->assertTrue(KPIStatus::getAggregatedStatus($withYellow)->isYellow());

        // Leere Liste = grün (neutral)
        $empty = [];
        $this->assertTrue(KPIStatus::getAggregatedStatus($empty)->isGreen());
    }

    /**
     * @test
     */
    public function kannCssKlasseAbrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertSame('status-green', $green->getCssClass());
        $this->assertSame('status-yellow', $yellow->getCssClass());
        $this->assertSame('status-red', $red->getCssClass());
    }

    /**
     * @test
     */
    public function kannEmojiAbrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertSame('✅', $green->getEmoji());
        $this->assertSame('⚠️', $yellow->getEmoji());
        $this->assertSame('❌', $red->getEmoji());
    }

    /**
     * @test
     */
    public function kannBenutzerfreundlicheNachrichtAbrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertSame('Aktuell', $green->getUserFriendlyMessage());
        $this->assertSame('Fällig bald', $yellow->getUserFriendlyMessage());
        $this->assertSame('Überfällig', $red->getUserFriendlyMessage());
    }

    /**
     * @test
     */
    public function valueObjectIstImmutable(): void
    {
        $status = KPIStatus::green();
        $originalString = $status->toString();

        // Versuche den Status zu "ändern" durch Erstellen eines neuen
        $newStatus = KPIStatus::red();

        // Original-Status sollte unverändert bleiben
        $this->assertSame('green', $originalString);
        $this->assertSame('green', $status->toString());
        $this->assertSame('red', $newStatus->toString());
    }

    /**
     * @test
     */
    public function kannStringDarstellungAusgeben(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertSame('green', (string) $green);
        $this->assertSame('yellow', (string) $yellow);
        $this->assertSame('red', (string) $red);
    }

    /**
     * @test
     */
    public function kannJsonSerialisierungHandhaben(): void
    {
        $status = KPIStatus::green();

        $json = json_encode($status);
        $this->assertSame('"green"', $json);

        $decoded = json_decode($json, true);
        $recreated = KPIStatus::fromString($decoded);

        $this->assertTrue($status->equals($recreated));
    }
}
