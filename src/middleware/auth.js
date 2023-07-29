import userUser from "../store/user"

// src/middleware/auth.js
export default function auth({ next, router }) {
    const { state } = userUser();
    if (! JSON.parse(localStorage.getItem('YourItem'))) {
      return router.push({ name: 'login.index' });
    } else
  
    return next();
  }