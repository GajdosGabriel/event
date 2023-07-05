import userUser from "../store/user"

// src/middleware/auth.js
export default function auth({ next, router }) {
    const { user } = userUser();
    if (!user.value) {
      return router.push({ name: 'login.index' });
    } else
  
    return next();
  }