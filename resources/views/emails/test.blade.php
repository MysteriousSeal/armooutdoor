<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email de test — Armo Outdoor</title>
</head>
{{-- Inline styles throughout: email clients strip almost everything else. --}}
<body style="margin: 0; padding: 0; background-color: #f7f6f4; font-family: 'Inter', -apple-system, 'Segoe UI', sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7f6f4; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 520px; background-color: #ffffff; border: 1px solid #e8e6e3;">
                    <tr>
                        <td style="padding: 28px 32px; border-bottom: 1px solid #e8e6e3;">
                            <span style="font-size: 18px; font-weight: 700; letter-spacing: 0.02em; color: #2c2c2c;">ARMO OUTDOOR</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 6px; font-size: 12px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #8b7e74;">Email de test</p>
                            <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 600; color: #2c2c2c;">La messagerie fonctionne.</h1>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #6b6b6b;">
                                Cet email a été envoyé depuis la page de test du back office.
                                S'il est arrivé jusqu'ici, l'envoi d'emails de la boutique est opérationnel.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7f6f4; border: 1px solid #e8e6e3;">
                                <tr>
                                    <td style="padding: 14px 18px;">
                                        <p style="margin: 0 0 4px; font-size: 13px; color: #6b6b6b;">
                                            <strong style="color: #2c2c2c;">Envoyé le :</strong> {{ $sentAt->format('d/m/Y à H:i:s') }}
                                        </p>
                                        <p style="margin: 0; font-size: 13px; color: #6b6b6b;">
                                            <strong style="color: #2c2c2c;">Via le transport :</strong> {{ $mailer }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 18px 32px; border-top: 1px solid #e8e6e3;">
                            <p style="margin: 0; font-size: 12px; color: #a8a29c;">
                                Armo Outdoor — email automatique, aucune action attendue.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
