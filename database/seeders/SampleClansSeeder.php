<?php

namespace Database\Seeders;

use App\Models\Clan;
use App\Models\Marriage;
use App\Models\Person;
use App\Services\Generations;
use App\Services\SiblingOrder;
use Illuminate\Database\Seeder;

/**
 * The prototype's three sample clans (implementation-notes.md §9): Santos, Dulnuan and
 * Menis. Between them they exercise every rule, including the §2.7 cross-clan example
 * (Paolo Santos = Annie Claire Menis, three children, one Santos and two Menis).
 *
 * Goes into the DEMO database (php artisan app:demo) and the test database — never the
 * registry.
 */
class SampleClansSeeder extends Seeder
{
    private int $clanId;

    public function __construct(private SiblingOrder $order, private Generations $generations) {}

    public function run(): void
    {
        /* ---- Santos clan ---- */
        $sc = Clan::create(['name' => 'Santos', 'origin_place' => 'San Roque', 'notes' => 'Transcribed from the handwritten family book. Page numbers are noted on each person where known.']);
        $this->clanId = $sc->id;
        $isko = $this->p(['given_name' => 'Isko', 'sex' => 'male', 'is_living' => false, 'notes' => 'Known by one name only, as on the first page of the record.']);
        $sela = $this->p(['given_name' => 'Sela', 'sex' => 'female', 'is_living' => false]);
        $sc->update(['founder_id' => $isko, 'founder_spouse_id' => $sela]);
        $this->m(['husband_id' => $isko, 'wife_id' => $sela, 'date_text' => 'before 1860', 'status' => 'widowed']);
        $juan = $this->kid(['given_name' => 'Juan', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1862-01-01', 'birth_date_text' => 'abt. 1862', 'death_date' => '1931-01-01', 'death_date_text' => '1931'], $isko, $sela);
        $petra = $this->p(['given_name' => 'Petra', 'last_name' => 'Lim', 'sex' => 'female', 'is_living' => false, 'birth_date_text' => '1866', 'death_date_text' => '1944']);
        $this->m(['husband_id' => $juan, 'wife_id' => $petra, 'date' => '1890-01-01', 'date_text' => '1890', 'place' => 'San Roque', 'status' => 'widowed']);
        $tomas = $this->kid(['given_name' => 'Tomas', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1865-01-01', 'birth_date_text' => '1865', 'death_date_text' => '1940'], $isko, $sela);
        $ambo = $this->kid(['given_name' => 'Ambo', 'sex' => 'male', 'is_living' => false, 'notes' => 'Died young. Remembered at the 2019 reunion: “there was one before Juan.” No dates.'], $isko, $sela);
        $this->order->place(Person::unscoped()->find($ambo), 1); // remembered later, inserted first
        $rosa = $this->kid(['given_name' => 'Rosa', 'last_name' => 'Santos', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1869-01-01', 'birth_date_text' => '1869', 'death_date_text' => '1950', 'is_subclan_head' => true, 'subclan_name' => 'Reyes branch'], $isko, $sela);
        $ignacio = $this->p(['given_name' => 'Ignacio', 'last_name' => 'Reyes', 'sex' => 'male', 'is_living' => false]);
        $this->m(['husband_id' => $ignacio, 'wife_id' => $rosa, 'date' => '1893-01-01', 'date_text' => '1893', 'status' => 'widowed']);
        $mc = $this->kid(['given_name' => 'Maria Clara', 'last_name' => 'Santos', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1898-01-01', 'birth_date_text' => '1898', 'death_date_text' => '1971'], $juan, $petra);
        $pedroC = $this->p(['given_name' => 'Pedro', 'last_name' => 'Cruz', 'sex' => 'male', 'is_living' => false, 'birth_date_text' => '1893', 'death_date_text' => '1960']);
        $this->m(['husband_id' => $pedroC, 'wife_id' => $mc, 'date_text' => '1920', 'date' => '1920-01-01', 'status' => 'widowed']);
        $andres = $this->kid(['given_name' => 'Andres', 'last_name' => 'Santos', 'nickname' => 'Ando', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1901-01-01', 'birth_date_text' => 'abt. 1901', 'birth_place' => 'San Roque', 'death_date' => '1988-03-14', 'death_date_text' => '14 Mar 1988', 'death_place' => 'Manila', 'occupation' => 'Carpenter', 'is_subclan_head' => true, 'subclan_name' => 'Ando’s line', 'notes' => 'Built the chapel at San Roque with his brothers-in-law. Called “Ando” by everyone but his mother.'], $juan, $petra);
        $lucia = $this->p(['given_name' => 'Lucia', 'last_name' => 'Reyes', 'sex' => 'female', 'is_living' => false, 'birth_date_text' => '1905', 'death_date_text' => '1979']);
        $this->m(['husband_id' => $andres, 'wife_id' => $lucia, 'date' => '1926-01-01', 'date_text' => '1926', 'place' => 'San Roque', 'status' => 'widowed']);
        $this->kid(['given_name' => 'Carmen', 'last_name' => 'Reyes', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1895-01-01', 'birth_date_text' => '1895', 'death_date_text' => '1970'], $ignacio, $rosa, 'mother');
        $this->kid(['given_name' => 'Vicente', 'last_name' => 'Reyes', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1899-01-01', 'birth_date_text' => '1899', 'death_date_text' => '1962'], $ignacio, $rosa, 'mother');
        $this->kid(['given_name' => 'Pilar', 'last_name' => 'Santos', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1905-01-01', 'birth_date_text' => '1905', 'death_date_text' => '1977', 'father_relation' => 'adopted', 'parentage_note' => 'Taken in by Tomas after the flood of 1910.'], $tomas, null);
        $this->kid(['given_name' => 'Josefa', 'last_name' => 'Cruz', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1921-01-01', 'birth_date_text' => '1921', 'death_date_text' => '2010'], $pedroC, $mc, 'mother');
        $this->kid(['given_name' => 'Benito', 'last_name' => 'Cruz', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1924-01-01', 'birth_date_text' => '1924', 'death_date_text' => '1991'], $pedroC, $mc, 'mother');
        $teo = $this->kid(['given_name' => 'Teodoro', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1928-03-02', 'birth_date_text' => '1928', 'death_date_text' => '2004'], $andres, $lucia);
        $this->kid(['given_name' => 'Elena', 'last_name' => 'Santos', 'nickname' => 'Lenny', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1931-01-01', 'birth_date_text' => '1931', 'death_date_text' => '2019'], $andres, $lucia);
        $this->kid(['given_name' => 'Ramon', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1934-01-01', 'birth_date_text' => 'abt. 1934', 'death_date_text' => '1999'], $andres, $lucia);
        $amparo = $this->p(['given_name' => 'Amparo', 'last_name' => 'Diaz', 'sex' => 'female', 'is_living' => false, 'birth_date_text' => '1932', 'death_date_text' => '2015']);
        $this->m(['husband_id' => $teo, 'wife_id' => $amparo, 'date' => '1955-01-01', 'date_text' => '1955', 'status' => 'widowed']);
        $this->kid(['given_name' => 'Andres', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => true, 'birth_date' => '1956-06-01', 'birth_date_text' => '1956', 'residence' => 'Quezon City'], $teo, $amparo);
        $liza = $this->kid(['given_name' => 'Liza', 'last_name' => 'Santos', 'sex' => 'female', 'is_living' => true, 'birth_date' => '1958-01-01', 'birth_date_text' => '1958'], $teo, $amparo);
        $marco = $this->kid(['given_name' => 'Marco', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => true, 'birth_date' => '1961-01-01', 'birth_date_text' => '1961'], $teo, $amparo);
        $paolo = $this->kid(['given_name' => 'Paolo', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => true, 'birth_date' => '1990-01-01', 'birth_date_text' => '1990', 'parentage_note' => 'Mother not named at the family’s request.'], $marco, null);
        $this->p(['given_name' => 'Tomasa', 'nickname' => 'Masang', 'sex' => 'female', 'birth_date_text' => 'before the war', 'notes' => 'From page 14 — parents not yet read.']);
        $this->p(['given_name' => 'Nicolas', 'sex' => 'male', 'birth_date_text' => 'abt. 1910']);

        /* ---- Dulnuan clan (intermarries with Santos) ---- */
        $dc = Clan::create(['name' => 'Dulnuan', 'origin_place' => 'Kiangan', 'notes' => 'Oral history from the elders, recorded at the 2019 reunion.']);
        $this->clanId = $dc->id;
        $pablo = $this->p(['given_name' => 'Pablo', 'last_name' => 'Dulnuan', 'sex' => 'male', 'is_living' => false, 'birth_date_text' => 'abt. 1880']);
        $ines = $this->p(['given_name' => 'Ines', 'sex' => 'female', 'is_living' => false]);
        $dc->update(['founder_id' => $pablo, 'founder_spouse_id' => $ines]);
        $this->m(['husband_id' => $pablo, 'wife_id' => $ines, 'date_text' => 'abt. 1905', 'status' => 'widowed']);
        $pedroD = $this->kid(['given_name' => 'Pedro', 'last_name' => 'Dulnuan', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1910-01-01', 'birth_date_text' => '1910', 'death_date_text' => '1985', 'is_subclan_head' => true, 'subclan_name' => 'Pedro’s line'], $pablo, $ines);
        $mariaD = $this->kid(['given_name' => 'Maria', 'last_name' => 'Dulnuan', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1913-01-01', 'birth_date_text' => '1913', 'death_date_text' => '1990'], $pablo, $ines);
        $carmenB = $this->p(['given_name' => 'Carmen', 'last_name' => 'Bautista', 'sex' => 'female', 'is_living' => false, 'birth_date_text' => '1915']);
        $this->m(['husband_id' => $pedroD, 'wife_id' => $carmenB, 'date_text' => '1936', 'date' => '1936-01-01', 'status' => 'widowed']);
        $jose = $this->p(['given_name' => 'Jose', 'last_name' => 'Aquino', 'sex' => 'male', 'is_living' => false]);
        $this->m(['husband_id' => $jose, 'wife_id' => $mariaD, 'date_text' => '1938', 'date' => '1938-01-01', 'status' => 'widowed']);
        $this->kid(['given_name' => 'Rosario', 'last_name' => 'Aquino', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1940-01-01', 'birth_date_text' => '1940', 'death_date_text' => '2012'], $jose, $mariaD, 'mother');
        $lito = $this->kid(['given_name' => 'Lito', 'last_name' => 'Dulnuan', 'sex' => 'male', 'is_living' => false, 'birth_date' => '1938-01-01', 'birth_date_text' => '1938', 'death_date_text' => '2001'], $pedroD, $carmenB);
        $benjie = $this->kid(['given_name' => 'Benjamin', 'last_name' => 'Dulnuan', 'nickname' => 'Benjie', 'sex' => 'male', 'is_living' => true, 'birth_date' => '1955-01-01', 'birth_date_text' => '1955'], $pedroD, $carmenB);
        $this->kid(['given_name' => 'Grace', 'last_name' => 'Dulnuan', 'sex' => 'female', 'is_living' => false, 'birth_date' => '1962-01-01', 'birth_date_text' => '1962', 'death_date_text' => '2020'], $lito, null);
        // Cross-clan marriage: Benjie (Dulnuan) = Liza (Santos). Their children are Dulnuan through their father.
        $this->m(['husband_id' => $benjie, 'wife_id' => $liza, 'date' => '1982-05-01', 'date_text' => 'May 1982', 'place' => 'San Roque', 'status' => 'married']);
        $this->kid(['given_name' => 'Mark', 'last_name' => 'Dulnuan', 'sex' => 'male', 'is_living' => true, 'birth_date' => '1984-01-01', 'birth_date_text' => '1984'], $benjie, $liza);
        $this->kid(['given_name' => 'Ana', 'last_name' => 'Dulnuan', 'sex' => 'female', 'is_living' => true, 'birth_date' => '1987-01-01', 'birth_date_text' => '1987', 'notes' => 'Twin of Joy — Ana is the elder by the family’s account.'], $benjie, $liza);
        $this->kid(['given_name' => 'Joy', 'last_name' => 'Dulnuan', 'sex' => 'female', 'is_living' => true, 'birth_date' => '1987-01-01', 'birth_date_text' => '1987', 'notes' => 'Twin of Ana.'], $benjie, $liza);
        $this->p(['given_name' => 'Tomas', 'last_name' => 'Dulnuan', 'sex' => 'male', 'birth_date_text' => 'abt. 1935', 'notes' => 'Named by an elder at the reunion; parents not confirmed.']);

        /* ---- Menis clan (intermarries with Santos) ---- */
        $mn = Clan::create(['name' => 'Menis', 'origin_place' => 'Tagudin', 'notes' => 'Recorded from Annie Claire’s family.']);
        $this->clanId = $mn->id;
        $ramonM = $this->p(['given_name' => 'Ramon', 'last_name' => 'Menis', 'sex' => 'male', 'is_living' => false, 'birth_date_text' => 'abt. 1935']);
        $claraM = $this->p(['given_name' => 'Clara', 'sex' => 'female', 'is_living' => true, 'birth_date_text' => '1940']);
        $mn->update(['founder_id' => $ramonM, 'founder_spouse_id' => $claraM]);
        $this->m(['husband_id' => $ramonM, 'wife_id' => $claraM, 'date_text' => '1960', 'status' => 'widowed']);
        $annie = $this->kid(['given_name' => 'Annie Claire', 'last_name' => 'Menis', 'sex' => 'female', 'is_living' => true, 'birth_date' => '1992-01-01', 'birth_date_text' => '1992'], $ramonM, $claraM);
        $this->kid(['given_name' => 'Jun', 'last_name' => 'Menis', 'sex' => 'male', 'is_living' => true, 'birth_date_text' => '1995'], $ramonM, $claraM);
        $this->m(['husband_id' => $paolo, 'wife_id' => $annie, 'date' => '2015-06-01', 'date_text' => 'June 2015', 'status' => 'married']);
        // One sibling set of three: Luis on Paolo's (Santos) line, Bea and Carlo on Annie's (Menis) line.
        $this->kid(['clan_id' => $sc->id, 'given_name' => 'Luis', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => true, 'birth_date_text' => '2016'], $paolo, $annie, 'father');
        $this->kid(['given_name' => 'Bea', 'last_name' => 'Santos', 'sex' => 'female', 'is_living' => true, 'birth_date_text' => '2018'], $paolo, $annie, 'mother');
        $this->kid(['given_name' => 'Carlo', 'last_name' => 'Santos', 'sex' => 'male', 'is_living' => true, 'birth_date_text' => '2021'], $paolo, $annie, 'mother');

        foreach ([$sc, $dc, $mn] as $c) {
            $this->generations->recomputeClan($c->id);
        }
    }

    /** A person in the current clan; sibling_order is the next free place in their set. */
    private function p(array $o): int
    {
        $o += ['clan_id' => $this->clanId];
        $o['sibling_order'] = $this->order->next($o['father_id'] ?? null, $o['mother_id'] ?? null);

        return Person::unscoped()->create($o)->id;
    }

    private function kid(array $o, ?int $father, ?int $mother, string $clanParent = 'father'): int
    {
        return $this->p($o + ['father_id' => $father, 'mother_id' => $mother, 'clan_parent' => $clanParent]);
    }

    private function m(array $o): void
    {
        Marriage::create($o);
    }
}
