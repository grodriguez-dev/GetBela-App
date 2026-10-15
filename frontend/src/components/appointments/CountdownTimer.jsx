import React, { useEffect, useState } from "react";
import { Clock } from "lucide-react";

const CountdownTimer = ({ endTime }) => {
  const [timeLeft, setTimeLeft] = useState("");
  const [isExpired, setIsExpired] = useState(false);

  useEffect(() => {
    const calculateTimeRemaining = () => {
      const now = new Date();
      const [hours, minutes, seconds] = endTime.split(":").map(Number);
      const targetTime = new Date();
      targetTime.setHours(hours, minutes, seconds || 0, 0);

      const diff = targetTime - now;

      if (diff <= 0) {
        const elapsedMs = Math.abs(diff);
        const diffMinutes = Math.floor(elapsedMs / 1000 / 60);
        const diffSecs = Math.floor((elapsedMs / 1000) % 60);

        setIsExpired(true);
        setTimeLeft(
          `+${diffMinutes} min ${diffSecs < 10 ? "0" : ""}${diffSecs} seg`,
        );
        return;
      }

      const diffMinutes = Math.floor(diff / 1000 / 60);
      const diffSecs = Math.floor((diff / 1000) % 60);

      setTimeLeft(
        `${diffMinutes} min ${diffSecs < 10 ? "0" : ""}${diffSecs} seg`,
      );
    };

    calculateTimeRemaining();
    const interval = setInterval(calculateTimeRemaining, 1000);

    return () => clearInterval(interval);
  }, [endTime]);

  return (
    <div
      className={`text-xs font-bold px-3 py-1.5 rounded-xl border flex items-center gap-1.5 ${
        isExpired
          ? "bg-red-50 text-red-700 border-red-200"
          : "bg-blue-50 text-blue-700 border-blue-200"
      }`}
    >
      <Clock className="w-3.5 h-3.5 animate-spin" />
      <span>
        {isExpired ? `Tiempo excedido: ${timeLeft}` : `Restan: ${timeLeft}`}
      </span>
    </div>
  );
};

export default CountdownTimer;