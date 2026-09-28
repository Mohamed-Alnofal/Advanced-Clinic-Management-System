importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js");

// نفس إعدادات مشروع Firebase الخاصة بك
firebase.initializeApp({
    apiKey: "AIzaSyAhacXlr6OwNYVwHKxI0QyPu1gGUi9C25g",
    authDomain: "clinicmanagementsystem-6a7f2.firebaseapp.com",
    projectId: "clinicmanagementsystem-6a7f2",
    messagingSenderId: "862133670748",
    appId: "1:862133670748:web:08c8afd9c5ae78b233a799",
});

const messaging = firebase.messaging();

// 👇 التعديل الثاني: الاستماع للإشعارات عندما يكون المتصفح مغلقاً أو في الخلفية (Background)
messaging.onBackgroundMessage((payload) => {
    console.log("تم استقبال إشعار في الخلفية (Background):", payload);

    // قراءة البيانات بمرونة من كلا الهيكليتين المحتملتين لمنع ظهور إشعار فارغ
    const title = payload.notification?.title || payload.data?.title || "تنبيه من نظام العيادة";
    const body = payload.notification?.body || payload.data?.body || "لديك تحديث جديد في لوحة التحكم.";

    const notificationOptions = {
        body: body,
        icon: "/icon.png", // رابط أيقونة العيادة أو التطبيق
        badge: "/badge.png", // أيقونة صغيرة تظهر في شريط إشعارات أندرويد
        data: payload.data   // الاحتفاظ بالبيانات الإضافية إذا أردت فتح رابط معين عند الضغط
    };

    // إظهار الإشعار على شاشة النظام
    return self.registration.showNotification(title, notificationOptions);
});