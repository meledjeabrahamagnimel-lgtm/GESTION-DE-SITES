<?php

namespace App\Domain\Messagerie\Services;

use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Messagerie\Models\PieceJointe;
use App\Domain\Shared\Models\NotificationApp;
use App\Domain\Shared\Services\Notificateur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class Messagerie
{
    /**
     * Ouvre une conversation avec les destinataires autorisés et y dépose le premier message.
     *
     * @param  array<int, int|string>  $destinataireIds
     * @param  array<int, TemporaryUploadedFile>  $fichiers
     */
    public static function ouvrir(User $expediteur, array $destinataireIds, ?string $sujet, string $corps, array $fichiers = []): Conversation
    {
        $destinataires = AnnuaireMessagerie::filtrer($expediteur, $destinataireIds);

        if ($destinataires === []) {
            throw ValidationException::withMessages([
                'destinataires' => "Choisissez au moins un destinataire auquel vous êtes autorisé à écrire.",
            ]);
        }

        return DB::transaction(function () use ($expediteur, $destinataires, $sujet, $corps, $fichiers) {
            $conversation = Conversation::create([
                'entreprise_id' => $expediteur->entreprise_id,
                'sujet' => $sujet ?: null,
                'cree_par' => $expediteur->id,
            ]);

            $conversation->participants()->attach($expediteur->id, ['lu_le' => now()]);
            $conversation->participants()->attach($destinataires);

            self::deposer($conversation, $expediteur, $corps, $fichiers);

            return $conversation;
        });
    }

    /**
     * Ajoute un message dans une conversation existante et prévient les autres participants.
     *
     * @param  array<int, TemporaryUploadedFile>  $fichiers
     */
    public static function repondre(Conversation $conversation, User $expediteur, string $corps, array $fichiers = []): Message
    {
        // Un participant garde le droit de répondre même si l'annuaire a changé depuis :
        // la seule vérification est donc l'appartenance à la conversation.
        abort_unless($conversation->participants()->whereKey($expediteur->id)->exists(), 403);

        return DB::transaction(fn () => self::deposer($conversation, $expediteur, $corps, $fichiers));
    }

    /**
     * @param  array<int, TemporaryUploadedFile>  $fichiers
     */
    private static function deposer(Conversation $conversation, User $expediteur, string $corps, array $fichiers): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'expediteur_id' => $expediteur->id,
            'corps' => trim($corps),
        ]);

        foreach ($fichiers as $fichier) {
            $chemin = $fichier->store('messagerie/'.$conversation->id, 'public');

            PieceJointe::create([
                'message_id' => $message->id,
                'nom_original' => $fichier->getClientOriginalName(),
                'chemin' => $chemin,
                'type_mime' => $fichier->getMimeType(),
                'taille' => Storage::disk('public')->size($chemin),
            ]);
        }

        $conversation->forceFill(['dernier_message_le' => $message->created_at])->save();

        // L'expéditeur est à jour par construction : il vient d'écrire.
        $conversation->participants()->updateExistingPivot($expediteur->id, ['lu_le' => now()]);

        $autres = $conversation->participants()
            ->where('users.id', '!=', $expediteur->id)
            ->pluck('users.id');

        Notificateur::pourPlusieurs(
            destinataires: $autres,
            titre: 'Message de '.$expediteur->name,
            corps: str($message->corps)->limit(120)->value(),
            canal: NotificationApp::CANAL_MESSAGE,
            niveau: NotificationApp::NIVEAU_INFO,
            lien: route('messages', ['conversation' => $conversation->id]),
        );

        return $message;
    }

    /** Marque la conversation comme lue pour cet utilisateur. */
    public static function marquerLue(Conversation $conversation, User $utilisateur): void
    {
        $conversation->participants()->updateExistingPivot($utilisateur->id, ['lu_le' => now()]);

        NotificationApp::query()
            ->where('user_id', $utilisateur->id)
            ->where('canal', NotificationApp::CANAL_MESSAGE)
            ->whereNull('lu_le')
            ->where('lien', route('messages', ['conversation' => $conversation->id]))
            ->update(['lu_le' => now()]);
    }

    /**
     * Identifiants des conversations comportant au moins un message non lu.
     *
     * @return array<int, int>
     */
    public static function conversationsNonLues(User $utilisateur): array
    {
        return DB::table('conversation_participants as cp')
            ->join('messages as m', 'm.conversation_id', '=', 'cp.conversation_id')
            ->where('cp.user_id', $utilisateur->id)
            ->where('m.expediteur_id', '!=', $utilisateur->id)
            ->where(fn ($q) => $q->whereNull('cp.lu_le')->orWhereColumn('m.created_at', '>', 'cp.lu_le'))
            ->distinct()
            ->pluck('cp.conversation_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
