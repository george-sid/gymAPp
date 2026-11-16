<?php

namespace Database\Seeders;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Database\Seeder;

/**
 * A starter library of common exercises. Safe to run more than once.
 */
class ExerciseLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $library = [
            MuscleGroup::Chest->value => [['Πιέσεις πάγκου με μπάρα', 'Μπάρα'], ['Πιέσεις επικλινούς με αλτήρες', 'Αλτήρες'], ['Κάμψεις (push-ups)', null], ['Ανοίγματα σε τροχαλία', 'Τροχαλία']],
            MuscleGroup::Back->value => [['Έλξεις μονόζυγου', 'Μονόζυγο'], ['Κωπηλατική με μπάρα', 'Μπάρα'], ['Έλξεις τροχαλίας εμπρός (lat pulldown)', 'Τροχαλία'], ['Κωπηλατική με αλτήρα', 'Αλτήρες']],
            MuscleGroup::Shoulders->value => [['Πιέσεις ώμων με αλτήρες', 'Αλτήρες'], ['Πλάγιες εκτάσεις', 'Αλτήρες'], ['Face pulls', 'Τροχαλία']],
            MuscleGroup::Arms->value => [['Κάμψεις δικεφάλων με μπάρα', 'Μπάρα'], ['Κάμψεις σφυριά', 'Αλτήρες'], ['Εκτάσεις τρικεφάλων σε τροχαλία', 'Τροχαλία'], ['Βυθίσεις (dips)', 'Δίζυγο']],
            MuscleGroup::Legs->value => [['Καθίσματα με μπάρα (back squat)', 'Μπάρα'], ['Goblet squat', 'Αλτήρας / Kettlebell'], ['Προβολές', 'Αλτήρες'], ['Πρέσα ποδιών', 'Μηχάνημα'], ['Ρουμάνικες άρσεις θανάτου', 'Μπάρα'], ['Κάμψεις ποδιών', 'Μηχάνημα']],
            MuscleGroup::Glutes->value => [['Hip thrust', 'Μπάρα'], ['Γέφυρα γλουτών', null], ['Bulgarian split squat', 'Αλτήρες']],
            MuscleGroup::Core->value => [['Σανίδα (plank)', null], ['Πλάγια σανίδα', null], ['Dead bug', null], ['Pallof press', 'Λάστιχο / Τροχαλία']],
            MuscleGroup::FullBody->value => [['Άρσεις θανάτου (deadlift)', 'Μπάρα'], ['Kettlebell swings', 'Kettlebell'], ['Burpees', null]],
            MuscleGroup::Cardio->value => [['Ποδήλατο', 'Ποδήλατο'], ['Κωπηλατικό', 'Κωπηλατικό'], ['Σχοινάκι', 'Σχοινάκι']],
            MuscleGroup::Mobility->value => [['Cat-cow', null], ['Διατάσεις ισχίου (90/90)', null]],
        ];

        foreach ($library as $muscleGroup => $exercises) {
            foreach ($exercises as [$name, $equipment]) {
                Exercise::query()->firstOrCreate(
                    ['name' => $name],
                    ['muscle_group' => $muscleGroup, 'equipment' => $equipment],
                );
            }
        }
    }
}
