{{--
    Gabarit de courriel : tables et styles en ligne, comme l'exigent les messageries
    (Outlook et Gmail ignorent la plupart des feuilles de style et les mises en page
    modernes). Trois blocs : l'en-tête au logo de l'entreprise, le corps du message,
    le pied aux coordonnées du cabinet éditeur.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
    style="background:#F5F3EE; margin:0; padding:24px 0; font-family:Segoe UI, Arial, sans-serif;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                style="width:600px; max-width:100%; background:#FFFFFF; border-radius:10px; overflow:hidden;">

                {{-- ------------------------------------------------- En-tête --}}
                <tr>
                    <td style="background:#191B20; padding:22px 28px;" align="center">
                        @if ($logo)
                            <img src="{{ $message->embed($logo) }}" alt="{{ $entreprise?->nom }}"
                                width="150" style="display:block; height:auto; max-width:150px; margin:0 auto 10px;">
                        @endif
                        <div style="color:#FFFFFF; font-size:19px; font-weight:700; letter-spacing:.3px;">
                            {{ $entreprise?->nom ?? "L'Artisan Automobile" }}
                        </div>
                        <div style="color:#C9CBD0; font-size:12.5px; margin-top:3px;">
                            Gestion de sites — suivi des opérations
                        </div>
                    </td>
                </tr>

                {{-- ---------------------------------------------------- Corps --}}
                <tr>
                    <td style="padding:30px 32px 8px; color:#26282E;">
                        <p style="margin:0 0 16px; font-size:17px; font-weight:700; color:#191B20;">
                            Bienvenue {{ $nom }},
                        </p>

                        <p style="margin:0 0 14px; font-size:14.5px; line-height:1.65;">
                            Vous voici sur <b>L'ARTISAN — Gestion de sites</b>, l'application de suivi des
                            opérations de L'Artisan Automobile.
                        </p>

                        <p style="margin:0 0 18px; font-size:14.5px; line-height:1.65;">
                            Votre compte a été créé par le cabinet <b>{{ $cabinet['nom'] }}</b>.
                        </p>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                            style="background:#F7F6F2; border-radius:8px; margin:0 0 22px;">
                            <tr>
                                <td style="padding:16px 18px; font-size:14px; color:#3A3D44; line-height:1.75;">
                                    <b style="color:#191B20;">Identifiant :</b>
                                    {{ $utilisateur->email }}<br>
                                    <b style="color:#191B20;">Rôle :</b> {{ $roleLisible }}
                                    @if ($perimetre)
                                        <br><b style="color:#191B20;">Périmètre :</b> {{ $perimetre }}
                                    @endif
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px; font-size:14.5px; line-height:1.65;">
                            Suivez le lien ci-dessous pour vous connecter. À votre <b>première connexion</b>,
                            l'application vous demandera de <b>changer votre mot de passe</b> : choisissez-en
                            un que vous seul connaissez.
                        </p>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
                            <tr>
                                <td align="center" bgcolor="#C8102E" style="border-radius:7px;">
                                    <a href="{{ $lienConnexion }}"
                                        style="display:inline-block; padding:13px 30px; font-size:15px; font-weight:700;
                                               color:#FFFFFF; text-decoration:none; border-radius:7px;">
                                        Me connecter
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 22px; font-size:12.5px; color:#6B6E76; line-height:1.6;">
                            Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>
                            <span style="color:#C8102E; word-break:break-all;">{{ $lienConnexion }}</span>
                        </p>

                        <p style="margin:0 0 4px; font-size:12.5px; color:#6B6E76; line-height:1.6;">
                            Pour votre sécurité, ce message ne contient pas votre mot de passe : il vous est
                            remis séparément. Ne le communiquez à personne.
                        </p>
                    </td>
                </tr>

                {{-- ----------------------------------------------------- Pied --}}
                <tr>
                    <td style="padding:22px 32px 26px;">
                        <div style="border-top:1px solid #E2E0D8; padding-top:18px;">
                            <div style="font-size:13.5px; font-weight:700; color:#191B20; margin-bottom:7px;">
                                Cabinet {{ $cabinet['nom'] }}
                            </div>
                            <div style="font-size:13px; color:#4B4E55; line-height:1.75;">
                                E-mail :
                                <a href="mailto:{{ $cabinet['email'] }}" style="color:#C8102E; text-decoration:none;">{{ $cabinet['email'] }}</a><br>
                                Fixe : {{ $cabinet['fixe'] }}<br>
                                Téléphone : {{ implode(' / ', $cabinet['telephones']) }}
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="font-size:11.5px; color:#8B8E96; margin-top:14px; font-family:Segoe UI, Arial, sans-serif;">
                Message automatique — merci de ne pas y répondre.
            </div>
        </td>
    </tr>
</table>
