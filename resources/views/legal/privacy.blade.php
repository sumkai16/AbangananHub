@extends('layouts.app', ['searchBar' => false])

@section('title', 'Privacy Policy — AbangananHub')
@section('themeable', '1')

@section('content')
    <x-legal-page
        eyebrow="Privacy Policy"
        title="Your data, handled with care."
        updated="September 19, 2026"
        intro="AbangananHub connects tenants and landlords in Cebu. This policy explains what personal information we collect, why we collect it, who sees it, and the choices you have. It is written to align with the Philippine Data Privacy Act of 2012 (Republic Act No. 10173)."
        :sections="[
            [
                'title' => 'Who we are',
                'body' => [
                    'AbangananHub is an online rental marketplace for the Cebu area. We operate the website and the accounts, listings, messaging, reservation and payment features on it. In this policy, “we”, “us” and “AbangananHub” mean the operators of that platform, and “you” means anyone who visits the site or holds an account.',
                ],
            ],
            [
                'title' => 'Information we collect',
                'body' => [
                    'What we collect depends on how you use the platform:',
                    ['list' => [
                        'Account details: first and last name, email address, contact number and password (stored only as a secure hash).',
                        'Sign-in through Google or Facebook: the name, email address and profile identifier those providers share with us when you choose to log in that way.',
                        'Landlord verification: identity and property documents you upload so we can verify you and your listings.',
                        'Listings: property descriptions, addresses and map locations, prices, amenities, photos and videos you publish.',
                        'Activity: favorites, inquiries, reservations, rental agreements, reviews, reports you file, and notifications.',
                        'Messages you send to other users through the in-app chat.',
                        'Payment records: amounts, dates and status of rent or fees paid through the platform. Card and wallet details are handled by our payment provider and are not stored by us.',
                        'Technical data: device and browser type, IP address, pages visited and error logs, collected automatically to run and protect the service.',
                    ]],
                ],
            ],
            [
                'title' => 'How we use your information',
                'body' => [
                    ['list' => [
                        'To create and secure your account, and to sign you in.',
                        'To show listings and let tenants and landlords contact, reserve and agree terms with each other.',
                        'To verify landlords and listings, and to review reports of scams, abuse or inaccurate information.',
                        'To process payments and keep records of them.',
                        'To send service messages such as reservation updates, notifications and password resets.',
                        'To maintain, troubleshoot and improve the platform, and to detect and prevent fraud or misuse.',
                        'To meet our legal obligations.',
                    ]],
                    'We do not sell your personal information.',
                ],
            ],
            [
                'title' => 'Legal basis and consent',
                'body' => [
                    'We process your information because it is necessary to provide the service you asked for (for example, listing a property or reserving a unit), because you have given your consent (for example, by uploading verification documents or choosing social sign-in), and because we have a legitimate interest in keeping the platform safe and reliable. You can withdraw consent at any time, but some features may stop working if you do.',
                ],
            ],
            [
                'title' => 'Who we share it with',
                'body' => [
                    ['list' => [
                        'Other users, as needed for a rental: a landlord sees the name and contact details a tenant provides when they inquire or reserve, and a tenant sees a landlord’s public profile and listing. Your phone number is shown only where the feature says so.',
                        'Service providers that help us run the platform, such as hosting, email delivery and payment processing. They may use your data only to provide their service to us.',
                        'Authorities and legal advisers, when required by law, court order or to protect rights and safety.',
                    ]],
                    'We do not share verification documents with tenants or the public.',
                ],
            ],
            [
                'title' => 'Third-party services',
                'body' => [
                    'The platform uses services operated by others, each with its own privacy policy: Google and Facebook for optional sign-in, PayMongo for payments, and OpenStreetMap-based map tiles (through CARTO) to display property locations. Your browser may connect to these services directly when you use the related feature.',
                ],
            ],
            [
                'title' => 'Cookies and local storage',
                'body' => [
                    'We use cookies that are necessary to keep you signed in and to protect forms against forgery. We also use your browser’s local storage for small conveniences, such as remembering whether you prefer the light or dark theme. We do not use advertising cookies.',
                ],
            ],
            [
                'title' => 'How long we keep it',
                'body' => [
                    'We keep account information while your account is active. Rental agreements, payment records and reports are kept for as long as needed for the purposes above and to meet legal, accounting and dispute-resolution requirements. When information is no longer needed, we delete or anonymize it.',
                ],
            ],
            [
                'title' => 'How we protect it',
                'body' => [
                    'We use reasonable organizational, technical and physical safeguards, including encrypted connections, hashed passwords and access controls that limit who can see sensitive documents. No system is perfectly secure, so please use a strong, unique password and keep it private. If a breach affecting your personal data occurs, we will notify you and the National Privacy Commission as required by law.',
                ],
            ],
            [
                'title' => 'Your rights',
                'body' => [
                    'Under the Data Privacy Act you have the right to:',
                    ['list' => [
                        'be informed about how your data is processed;',
                        'access the personal data we hold about you;',
                        'correct inaccurate or outdated data;',
                        'object to processing, or withdraw your consent;',
                        'request that your data be blocked, removed or deleted, where the law allows;',
                        'receive a copy of your data in a commonly used format;',
                        'claim compensation for damages caused by inaccurate, unlawfully obtained or unauthorized use of your data; and',
                        'file a complaint with the National Privacy Commission (privacy.gov.ph).',
                    ]],
                    'You can update most of your details from your account settings. For anything else, contact us using the details in the last section.',
                ],
            ],
            [
                'title' => 'Children',
                'body' => [
                    'AbangananHub is intended for adults who can enter into a rental agreement. We do not knowingly collect personal information from anyone under 18. If you believe a minor has given us their data, tell us and we will remove it.',
                ],
            ],
            [
                'title' => 'Changes to this policy',
                'body' => [
                    'We may update this policy as the platform or the law changes. The “Last updated” date at the top shows the current version. If a change is significant, we will notify you in the platform or by email.',
                ],
            ],
            [
                'title' => 'Contact us',
                'body' => [
                    'For privacy questions or to exercise any of your rights, contact the AbangananHub team through Report a Problem in your account, and mark your message “Privacy request”. We will respond within a reasonable time and may ask you to verify your identity first.',
                ],
            ],
        ]" />
@endsection
