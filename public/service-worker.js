// service-worker.js
self.addEventListener('push', function(event) {
    console.log('Push event received');
    
    if (event.data) {
      const data = event.data.json();
      console.log('Push data:', data);
      
      const options = {
        body: data.notification.body,
        icon: data.notification.icon || '/favicon.ico',
        badge: data.notification.badge,
        data: data.notification.data,
        actions: data.notification.actions || []
      };
      
      event.waitUntil(
        self.registration.showNotification(data.notification.title, options)
      );
    } else {
      console.log('Push event has no data');
    }
  });
  
  self.addEventListener('notificationclick', function(event) {
    console.log('Notification click event:', event);
    event.notification.close();
    
    if (event.notification.data && event.notification.data.url) {
      event.waitUntil(
        clients.openWindow(event.notification.data.url)
      );
    }
  });