
// Extend window object for global variables
declare global {
  interface Window {
    Routing: {
      generate: (route: string, params?: Record<string, any>) => string;
    };
  }

  interface Number {
    formatMoney(): string;
  }
}

export {};
