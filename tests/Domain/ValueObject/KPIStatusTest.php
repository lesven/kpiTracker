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
    public function kann_gruenen_status_erstellen(): void
    {
        $status = KPIStatus::green();

        $this->assertTrue($status->isGreen());
        $this->assertFalse($status->isYellow());
        $this->assertFalse($status->isRed());
        $this->assertEquals('green', $status->toString());
    }

    /**
     * @test
     */
    public function kann_gelben_status_erstellen(): void
    {
        $status = KPIStatus::yellow();

        $this->assertFalse($status->isGreen());
        $this->assertTrue($status->isYellow());
        $this->assertFalse($status->isRed());
        $this->assertEquals('yellow', $status->toString());
    }

    /**
     * @test
     */
    public function kann_roten_status_erstellen(): void
    {
        $status = KPIStatus::red();

        $this->assertFalse($status->isGreen());
        $this->assertFalse($status->isYellow());
        $this->assertTrue($status->isRed());
        $this->assertEquals('red', $status->toString());
    }

    /**
     * @test
     */
    public function kann_status_aus_string_erstellen(): void
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
    public function wirft_exception_bei_ungueltigem_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid KPI status: invalid');

        KPIStatus::fromString('invalid');
    }

    /**
     * @test
     */
    public function kann_pruefen_ob_kritisch(): void
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
    public function kann_pruefen_ob_ok(): void
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
    public function kann_status_vergleichen_fuer_verschlechterung(): void
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
    public function kann_status_vergleichen_fuer_verbesserung(): void
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
    public function kann_gleichheit_pruefen(): void
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
    public function kann_hierarchie_wert_abrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertEquals(1, $green->getHierarchyValue());
        $this->assertEquals(2, $yellow->getHierarchyValue());
        $this->assertEquals(3, $red->getHierarchyValue());
    }

    /**
     * @test
     */
    public function kann_aggregierten_status_berechnen(): void
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
    public function kann_css_klasse_abrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertEquals('status-green', $green->getCssClass());
        $this->assertEquals('status-yellow', $yellow->getCssClass());
        $this->assertEquals('status-red', $red->getCssClass());
    }

    /**
     * @test
     */
    public function kann_emoji_abrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertEquals('✅', $green->getEmoji());
        $this->assertEquals('⚠️', $yellow->getEmoji());
        $this->assertEquals('❌', $red->getEmoji());
    }

    /**
     * @test
     */
    public function kann_benutzerfreundliche_nachricht_abrufen(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertEquals('Aktuell', $green->getUserFriendlyMessage());
        $this->assertEquals('Fällig bald', $yellow->getUserFriendlyMessage());
        $this->assertEquals('Überfällig', $red->getUserFriendlyMessage());
    }

    /**
     * @test
     */
    public function value_object_ist_immutable(): void
    {
        $status = KPIStatus::green();
        $originalString = $status->toString();

        // Versuche den Status zu "ändern" durch Erstellen eines neuen
        $newStatus = KPIStatus::red();

        // Original-Status sollte unverändert bleiben
        $this->assertEquals('green', $originalString);
        $this->assertEquals('green', $status->toString());
        $this->assertEquals('red', $newStatus->toString());
    }

    /**
     * @test
     */
    public function kann_string_darstellung_ausgeben(): void
    {
        $green = KPIStatus::green();
        $yellow = KPIStatus::yellow();
        $red = KPIStatus::red();

        $this->assertEquals('green', (string) $green);
        $this->assertEquals('yellow', (string) $yellow);
        $this->assertEquals('red', (string) $red);
    }

    /**
     * @test
     */
    public function kann_json_serialisierung_handhaben(): void
    {
        $status = KPIStatus::green();

        $json = json_encode($status);
        $this->assertEquals('"green"', $json);

        $decoded = json_decode($json, true);
        $recreated = KPIStatus::fromString($decoded);
        
        $this->assertTrue($status->equals($recreated));
    }
}