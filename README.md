DMAPNServer
===========

Apple APN Push Notification & Feedback Provider

This is a set of open source PHP classes to interact with the [Apple Push Notification service](http://developer.apple.com/library/mac/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/ApplePushService/ApplePushService.html) for iOS and OS X (Mountain Lion).
I've wrote it for my own needs and due to some limitations on my virtual server platform it does not support multiple concurrent threads sending
(okay in fact i'm talking about multiple forked processes).
It's very simple to use.

### GENERATE A PUSH CERTIFICATE

* Login into the iOS or OSX Developer Program Portal from [this page](https://developer.apple.com)
* [Choose](https://developer.apple.com/ios/manage/bundles/index.action) or [create a new App ID](https://developer.apple.com/ios/manage/bundles/add.action) for your application (without a wildcard): for example com.danielemargutti.romapocket.
* Click Configure link next to your AppID and then click on the button to start the wizard to generate a new push SSL certificate (you can create two kinds of certificates, sandbox/development can be used during program development, production is used when you want to publish your app on appstore/adhoc). [More at Apple Docs](https://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/ProvisioningDevelopment/ProvisioningDevelopment.html)
* Download your certificate (it's a .cer file) then double click on it. Your certificate will be imported in your Mac Keychain Assistant (*/Applications/Utilities/Keychain Access*).
* Find your certificate in certificate's list, then expand it: select it and it's private key then control click to show 'Export 2 elements…'; you will save a new .p12 certificate
* Now you need to convert .p12 certificate to PEM. You can use openssl from terminal (*openssl pkcs12 -in p12-certificate-path -out pem-file-destination-path -nodes -clcerts*) or [this web utility](https://www.sslshopper.com/ssl-converter.html)
* You have your PEM certificate! Now come back to Developer Program and generate your Development/Distribution provisioning profile for your app. Follow [Local and Push Notification Programming Guide/Registering for Remote Notifications](http://developer.apple.com/library/ios/#DOCUMENTATION/NetworkingInternet/Conceptual/RemoteNotificationsPG/IPhoneOSClientImp/IPhoneOSClientImp.html) and implement client side part of it.

### HOW TO SEND A PUSH WITH DMAPNServer

```php
$APN = new DMAPNPushServer(false);
$APN->setCertificate("production/sandbox_cert.pem","password-if-any");
$message = new DMAPNMessage("device_uuid","message");
$APN->addMessage($message);
$APN->connect();
$APN->sendMessages();
```

### HOW TO GET FEEDBACK FROM DMAPNServer

Sometimes APNs might attempt to deliver notifications for an application on a device, but the device may repeatedly refuse delivery because there is no target application. 

This often happens when the user has uninstalled the application. In these cases, APNs informs the provider through a feedback service that the provider connects with.

The feedback service maintains a list of devices per application for which there were recent, repeated failed attempts to deliver notifications. The provider should obtain this list of devices and stop sending notifications to them. For more on this service, see “The Feedback Service.”

```php
$APNFeedback = new DMAPNFeedbackService(false);
$APNFeedback->setCertificate("production_cert.pem","");
$unregistered_devices = $APNFeedback->unregisteredDevices();
if ($unregistered_devices == false)
echo "failed to query feedback server";
else
echo vardump($unregistered_devices);
```