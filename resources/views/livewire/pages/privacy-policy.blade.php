<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.legal')]
#[Title('Privacy Policy')]
class extends Component {
}; ?>

<div class="space-y-6 text-slate-700 leading-relaxed">
    <div>
        <h1 class="text-3xl font-bold text-slate-900 mb-2">Privacy Policy</h1>
        <p class="text-sm text-slate-500">Last updated: {{ date('F Y') }}</p>
    </div>

    <p>
        Beanstack is committed to preserving your privacy. Please read the following privacy policy to understand how we use and protect the information obtained from those visiting and using Pkpms App (the &ldquo;App&rdquo;). This policy is your guide to how we will handle information we learn about you from your visit to our App.
    </p>

    <p>
        By using the App you consent to the collection/retention and use of your personal information in accordance with this policy.
    </p>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Information that we collect from you</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li>We collect information about you and providing such information is strictly voluntary:</li>
            <li>when you provide us with your personal details (such as your name, contact details, e-mail address etc) by registering with us or submitting an enquiry via the App;</li>
            <li>when you respond to surveys which we ask you to complete for research purposes; and</li>
            <li>from your usage of the App and any other information you post on the App, e-mail or otherwise send to us.</li>
        </ul>
        <p class="mt-4">
            We collect and store only the following information about you: the name of the domain from which you access the Internet (for example, aol.com, if you are connecting from an America Online account, or princeton.edu if you are connecting from Princeton University&rsquo;s domain), the date and time you access our App, and the Internet address of the App from which you linked to our App.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Use of your information</h2>
        <p>We use your information</p>
        <ul class="list-disc pl-6 space-y-2 mt-3">
            <li>to enable us to supply you with the goods, services and information which you have requested;</li>
            <li>to ensure that content from the App is presented in the most effective manner for you and for your computer;</li>
            <li>to analyse the information we collect so that we can administer, support and improve and develop the App;</li>
            <li>to provide you with information, products or services that you request from us or which we feel may interest you, where you have consented to be contacted for such purposes;</li>
            <li>to notify you about changes to our service.</li>
        </ul>
        <p class="mt-4">
            We also use the information we collect to measure the number of visitors to the different sections of our App, and to help us make our App more useful to visitors.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Use of Links</h2>
        <p>
            Throughout our App, we provide links to other servers which may contain information of interest to our readers. We take no responsibility for, and exercise no control over, the organizations, views, or accuracy of the information contained on other servers. Creating a text link from your app to our App does not require permission. If you have a link you&rsquo;d like us to consider adding to our App, please send an email to <a href="mailto:info@beanstacktech.com?subject=Link%20request" class="text-[#ed1c24] hover:underline">info@beanstacktech.com</a> with the subject &ldquo;Link request.&rdquo;
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Use of Text and Images</h2>
        <p>
            If you would like to publish information that you find on our App, please send your request to <a href="mailto:info@beanstacktech.com" class="text-[#ed1c24] hover:underline">info@beanstacktech.com</a>.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Accessibility</h2>
        <p>
            This App is designed to be accessible to visitors with disabilities, and to comply with federal guidelines concerning accessibility. We welcome your comments. If you have suggestions on how to make the App more accessible, please contact us at <a href="mailto:info@beanstacktech.com" class="text-[#ed1c24] hover:underline">info@beanstacktech.com</a>.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Kids and Privacy</h2>
        <p>
            For children who visit our App, special rules apply. We do not request personal information about children, such as first and last name or street address and city. When kids send email to us, their online contact information (email address) is not used to re-contact them and is not maintained in retrievable form.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Storage of your information</h2>
        <p>
            Where we have given you (or where you have chosen) a password which enables you to access certain parts of the App, you are responsible for keeping this password confidential. We ask you not to share a password with anyone. Unfortunately, the transmission of information via the internet is not completely secure. Although we will do our best to protect your personal data, we cannot guarantee the security of your data transmitted to the App; any transmission is at your own risk. Once we have received your information, we will use strict procedures and security features to try to prevent unauthorized access.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Disclosure of your information</h2>
        <p>
            Authorized personnel within Beanstack will be able to access the information you provide to us. We may also disclose your information to other third parties who act for us for the purposes set out in the policy or for purposes approved by you. Please note that countries outside Malaysia may not always have the same strong data protection laws. However, we will always take steps to ensure that your information is used by third parties in accordance with the terms of this Privacy Policy. Unless required or permitted do so by law, we will not otherwise share, sell or distribute any of the information you provide to us without your consent.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">IP addresses and cookies</h2>
        <p>
            We may collect information about your phone, including where available your IP address, operating system and browser type. This information is statistical data about users&rsquo; browsing actions and patterns, does not identify any individual. It is used to inform improvements to the App, for system administration, and to report aggregate information to third parties.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Security</h2>
        <p>
            We employ security measures to protect your information from access by unauthorized persons and against unlawful processing, accidental loss, destruction or damage. Please help us keep our records updated by informing us of any changes to your email address and other contact details.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Online Profile Updates and Donations</h2>
        <p>
            If you complete the Profile update form and share your personally identifying information, this information will be used only to provide you with more targeted content. We may use your contact information to send further information about our organization or to contact you when necessary. We may contact you by e-mail and SMS. If you change your mind about being contacted in the future by any of these means, then you may always opt-out of receiving future mailings; see the &ldquo;Opt Out&rdquo; section below.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Sending us an Email</h2>
        <p>
            You also may decide to send us personally identifying information, for example, in an electronic mail message containing a question or comment, or by filling out a App form that provides us this information. We use personally identifying information from email primarily to respond to your requests. We may forward your email to other employees who are better able to answer your questions. We may also use your email to contact you in the future about our programmes that may be of interest. We want to be very clear: We will not obtain personally identifying information about you when you visit our App, unless you choose to provide such information to us. Providing such information is strictly voluntary. Except as might be required by law, we do not share any information we receive with any outside parties. If you sign up for one of our email lists, we will only send you the kinds of information you have requested. We won&rsquo;t share your name or email address with any outside parties.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Opt-Out or Change Your Contact Information</h2>
        <p>
            Our App provides users the opportunity to opt-out of receiving communications from us. You may choose to receive only specific communications or none. You may also update your contact information previously provided to us. You cannot remove yourself from our database, but you can prevent unwanted communication. Kindly write to us at <a href="mailto:info@beanstacktech.com" class="text-[#ed1c24] hover:underline">info@beanstacktech.com</a> if you wish to opt-out or change your contact information.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Changes to our Privacy Policy</h2>
        <p>
            We may amend this policy from time to time. If we make any substantial changes, we will notify you by posting a prominent notice on the App. We encourage Users to frequently check this page for any changes to stay informed about how we are helping to protect the personal information we collect. You acknowledge and agree that it is your responsibility to review this privacy policy periodically and become aware of modifications.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Questions about our policies</h2>
        <p>
            You have the right to ask for a copy of the information held by us in our records in return for which we charge a small fee. If you have any questions about this privacy statement, the practices of this App, or your dealings with this App, you can contact us at: <a href="mailto:info@beanstacktech.com" class="text-[#ed1c24] hover:underline">info@beanstacktech.com</a>.
        </p>
    </section>

    <section>
        <h2 class="text-xl font-semibold text-slate-900 mt-8 mb-3">Governing law</h2>
        <p>
            These Terms and Conditions shall be governed and construed in accordance with the laws of Malaysia, whose courts shall have exclusive jurisdiction, although we retain the right to bring proceedings against you for breach of these conditions in your country of residence or other relevant country.
        </p>
    </section>
</div>
