<?php

/*
 * Screen Ads terms and content policy. Every user accepts the current version before they can
 * load money, upload or book; the acceptance is stored with a hash of this exact text.
 * Change `version` whenever the text changes so everyone is asked again.
 * Have a lawyer review the wording before the real launch.
 */
return [
    'version' => '2026-10-30',

    'title' => 'Terms of use and content policy',

    'intro' => 'Screen Ads shows advertisements on public screens that anyone, including children, can see. '
        .'By continuing you agree to these terms. Breaking them leads to removal of your ads, '
        .'suspension of your account and legal action.',

    'prohibited' => [
        ['title' => 'Sexual content and pornography', 'body' => 'Nudity, sexual acts, sexually suggestive images or text, and anything sexualising minors.'],
        ['title' => 'Violence and harmful content', 'body' => 'Graphic violence, gore, self-harm, cruelty to people or animals, or content that encourages any of these.'],
        ['title' => 'Weapons and illegal goods', 'body' => 'Illegal weapons, explosives, ammunition, and the sale of drugs or any goods or services that are illegal in Nepal.'],
        ['title' => 'Harassment and hate', 'body' => 'Content that harasses, bullies, threatens or defames anyone, or attacks people for caste, ethnicity, religion, gender, disability or other identity.'],
        ['title' => 'Fraud and misleading claims', 'body' => 'Scams, fake offers, false health or financial claims, and impersonation of people, brands or government bodies.'],
        ['title' => 'Other people’s rights', 'body' => 'Images, music, logos or text you do not have the right to use, and personal details of anyone without consent.'],
    ],

    'rules' => [
        'Every ad is checked by the RentALL Nepal review team before it plays. We can refuse or remove any ad, with a reason.',
        'Screen owners can also refuse whole categories, such as alcohol, tobacco or political ads, on their screens.',
        'You load money into your wallet before booking. The cost of a booking is held from your balance and each play is charged as it happens. Plays that do not happen are returned to your balance.',
        'You must give true identity details and a valid document. Accounts with false documents are closed.',
        'We keep records of uploads, approvals, plays and payments. They cannot be edited and can be shared with the authorities.',
    ],

    'legal' => 'Publishing illegal, obscene or defamatory material is an offence under the Electronic Transactions Act, 2063, '
        .'the Muluki Criminal Code, 2074 and the Advertisement (Regulation) Act, 2076. If your content breaks the law we will '
        .'remove it, suspend your account and report it, together with your identity details and our records, to Nepal Police and the relevant authorities. '
        .'You are responsible for any loss caused to RentALL Nepal, screen owners or others.',

    // Shown again, short, on every upload.
    'declaration' => 'I confirm this ad is mine to use and contains no sexual or pornographic content, violence, weapons, '
        .'harassment, hate, fraud or anything illegal. I understand that breaking these rules leads to suspension and legal action.',
];
