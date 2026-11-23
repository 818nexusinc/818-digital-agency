<?php
/* French patterns for strings that carry a price, a count or a name. {n} is a captured group, itself passed through t(). */
return [
  '/^From (.+)\/mo$/u' => 'À partir de {1}/mois',
  '/^From (.+)\/yr$/u' => 'À partir de {1}/an',
  '/^✓ From (.+)$/u' => '✓ À partir de {1}',
  '/^From (.+)$/u' => 'À partir de {1}',
  '/^✓ (.+) flat$/u' => '✓ {1} forfaitaire',
  '/^(Starter|Business|Team|Launch|Grow|Scale|Care|Protect) (.+)\/mo$/u' => '{1} {2}/mois',
  '/^(.+)\/mo$/u' => '{1}/mois',
  '/^(.+)\/yr$/u' => '{1}/an',
  '/^(\d+)–(\d+) business days$/u' => '{1} à {2} jours ouvrables',
  '/^(\d+)–(\d+) weeks$/u' => '{1} à {2} semaines',
  '/^Add to cart · (.+)$/u' => 'Ajouter au panier · {1}',
  '/^Order report · (.+)$/u' => 'Commander le rapport · {1}',
  '/^NUANS name search \((.+)\)$/u' => 'Recherche de nom NUANS ({1})',
  '/^One-time payment of (.+) by card, or request an invoice\.$/u' => 'Paiement unique de {1} par carte, ou demandez une facture.',
  '/^Cart \((\d+) items?\)$/u' => 'Panier ({1} article(s))',
  '/^Then (.+)\/month until cancelled\.$/u' => 'Ensuite {1}/mois jusqu’à l’annulation.',
  '/^(.+) — (.+)$/u' => '{1} — {2}',
  '/^Welcome, (.+)$/u' => 'Bienvenue, {1}',
  '/^(.+) · Client portal$/u' => '{1} · Portail client',
  '/^(.+) \| 818 Digital Agency$/u' => '{1} | 818 Digital Agency',
];
