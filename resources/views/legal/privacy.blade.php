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
                'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21',
                'body' => [
                    'AbangananHub is an online rental marketplace for the Cebu area. We operate the website and the accounts, listings, messaging, reservation and payment features on it. In this policy, “we”, “us” and “AbangananHub” mean the operators of that platform, and “you” means anyone who visits the site or holds an account.',
                ],
            ],
            [
                'title' => 'Information we collect',
                'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9Z',
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
                'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a7.78 7.78 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28ZM15 12a3 3 0 11-6 0 3 3 0 016 0Z',
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
                'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0Z',
                'body' => [
                    'We process your information because it is necessary to provide the service you asked for (for example, listing a property or reserving a unit), because you have given your consent (for example, by uploading verification documents or choosing social sign-in), and because we have a legitimate interest in keeping the platform safe and reliable. You can withdraw consent at any time, but some features may stop working if you do.',
                ],
            ],
            [
                'title' => 'Who we share it with',
                'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z',
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
                'icon' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
                'body' => [
                    'The platform uses services operated by others, each with its own privacy policy: Google and Facebook for optional sign-in, PayMongo for payments, and OpenStreetMap-based map tiles (through CARTO) to display property locations. Your browser may connect to these services directly when you use the related feature.',
                ],
            ],
            [
                'title' => 'Cookies and local storage',
                'icon' => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
                'body' => [
                    'We use cookies that are necessary to keep you signed in and to protect forms against forgery. We also use your browser’s local storage for small conveniences, such as remembering whether you prefer the light or dark theme. We do not use advertising cookies.',
                ],
            ],
            [
                'title' => 'How long we keep it',
                'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0Z',
                'body' => [
                    'We keep account information while your account is active. Rental agreements, payment records and reports are kept for as long as needed for the purposes above and to meet legal, accounting and dispute-resolution requirements. When information is no longer needed, we delete or anonymize it.',
                ],
            ],
            [
                'title' => 'How we protect it',
                'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25Z',
                'body' => [
                    'We use reasonable organizational, technical and physical safeguards, including encrypted connections, hashed passwords and access controls that limit who can see sensitive documents. No system is perfectly secure, so please use a strong, unique password and keep it private. If a breach affecting your personal data occurs, we will notify you and the National Privacy Commission as required by law.',
                ],
            ],
            [
                'title' => 'Your rights',
                'icon' => 'M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z',
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
                'icon' => 'M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.25 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z',
                'body' => [
                    'AbangananHub is intended for adults who can enter into a rental agreement. We do not knowingly collect personal information from anyone under 18. If you believe a minor has given us their data, tell us and we will remove it.',
                ],
            ],
            [
                'title' => 'Changes to this policy',
                'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 18.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487Zm0 0L19.5 7.125',
                'body' => [
                    'We may update this policy as the platform or the law changes. The “Last updated” date at the top shows the current version. If a change is significant, we will notify you in the platform or by email.',
                ],
            ],
            [
                'title' => 'Contact us',
                'icon' => 'M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
                'body' => [
                    'For privacy questions or to exercise any of your rights, contact the AbangananHub team through Report a Problem in your account, and mark your message “Privacy request”. We will respond within a reasonable time and may ask you to verify your identity first.',
                ],
            ],
        ]" />
@endsection
